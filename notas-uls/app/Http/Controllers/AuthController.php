<?php
namespace App\Http\Controllers;

use App\Mail\CodigoRecuperacion;
use App\Mail\CodigoVerificacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 422);
        }
        if ($user->estado !== 'habilitado') {
            return response()->json(['message' => 'Usuario inhabilitado'], 403);
        }

        // El código por correo solo se pide la primera vez: si ya confirmó el 2FA, entra directo
        if ($user->two_factor_confirmed_at) {
            $token = $user->createToken('access', ['access'])->plainTextToken;

            return response()->json([
                'token'                => $token,
                'user'                 => $user->load('docente'),
                'must_change_password' => (bool) $user->must_change_password,
                'two_factor_omitido'   => true,
            ]);
        }

        // Primer ingreso: token temporal + código de 6 dígitos enviado al correo
        $token = $user->createToken('pre-2fa', ['pre-2fa'], now()->addMinutes(10))->plainTextToken;

        if (! $this->enviarCodigo($user)) {
            $user->tokens()->delete();
            return response()->json(['message' => 'No se pudo enviar el código al correo. Intenta de nuevo.'], 500);
        }

        return response()->json([
            'token'         => $token,
            'requires_code' => true,
            'correo'        => $this->ocultarCorreo($user->email),
        ]);
    }

    // Reenvía el código (máximo una vez por minuto)
    public function reenviarCodigo(Request $request)
    {
        $user = $request->user();

        if (Cache::has($this->claveEspera($user))) {
            return response()->json(['message' => 'Espera un minuto antes de pedir otro código'], 429);
        }
        if (! $this->enviarCodigo($user)) {
            return response()->json(['message' => 'No se pudo enviar el código al correo'], 500);
        }

        return response()->json(['correo' => $this->ocultarCorreo($user->email)]);
    }

    public function verify2fa(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);

        $user = $request->user();
        $clave = $this->claveCodigo($user);
        $guardado = Cache::get($clave);

        if (! $guardado) {
            return response()->json(['message' => 'El código venció. Solicita uno nuevo.'], 422);
        }
        if ($guardado['intentos'] >= 5) {
            Cache::forget($clave);
            return response()->json(['message' => 'Demasiados intentos. Solicita un código nuevo.'], 429);
        }
        if (! Hash::check($request->code, $guardado['hash'])) {
            $guardado['intentos']++;
            Cache::put($clave, $guardado, $guardado['expira']);
            return response()->json(['message' => 'Código inválido'], 422);
        }

        Cache::forget($clave);
        if (! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        $user->currentAccessToken()->delete();
        $token = $user->createToken('access', ['access'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->load('docente'),
            'must_change_password' => $user->must_change_password,
        ]);
    }

    private function claveCodigo(User $user): string { return '2fa_codigo_' . $user->id; }
    private function claveEspera(User $user): string { return '2fa_espera_' . $user->id; }

    // Genera un código nuevo, lo guarda cifrado por 10 minutos y lo envía por correo
    private function enviarCodigo(User $user): bool
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expira = now()->addMinutes(10);

        try {
            Mail::to($user->email)->send(new CodigoVerificacion($user, $codigo, 10));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el código 2FA: ' . $e->getMessage());
            return false;
        }

        Cache::put($this->claveCodigo($user), [
            'hash'     => Hash::make($codigo),
            'intentos' => 0,
            'expira'   => $expira,
        ], $expira);
        Cache::put($this->claveEspera($user), true, now()->addSeconds(60));

        return true;
    }

    // ta***@gmail.com
    private function ocultarCorreo(string $email): string
    {
        [$local, $dominio] = explode('@', $email, 2);
        return substr($local, 0, 2) . str_repeat('*', max(strlen($local) - 2, 3)) . '@' . $dominio;
    }

    // ---------- Recuperación de contraseña (código por correo) ----------

    // Paso 1: envía un código al correo. Responde igual exista o no la cuenta.
    public function olvidePassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $hash = sha1($data['email']);

        if (Cache::has('rec_espera_' . $hash)) {
            return response()->json(['message' => 'Espera un minuto antes de pedir otro código'], 429);
        }
        Cache::put('rec_espera_' . $hash, true, now()->addSeconds(60));

        $user = User::where('email', $data['email'])->where('estado', 'habilitado')->first();

        if ($user) {
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = now()->addMinutes(10);

            try {
                Mail::to($user->email)->send(new CodigoRecuperacion($user, $codigo, 10));
                Cache::put('rec_codigo_' . $hash, [
                    'hash'     => Hash::make($codigo),
                    'intentos' => 0,
                    'expira'   => $expira,
                ], $expira);
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el código de recuperación: ' . $e->getMessage());
            }
        }

        return response()->json(['message' => 'Si el correo está registrado, te enviamos un código de 6 dígitos.']);
    }

    // Paso 2: valida el código y guarda la nueva contraseña
    public function restablecerPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|digits:6',
            'password' => 'required|min:8|confirmed',
        ]);

        $clave = 'rec_codigo_' . sha1($data['email']);
        $guardado = Cache::get($clave);

        if (! $guardado) {
            return response()->json(['message' => 'El código venció o no es válido. Solicita uno nuevo.'], 422);
        }
        if ($guardado['intentos'] >= 5) {
            Cache::forget($clave);
            return response()->json(['message' => 'Demasiados intentos. Solicita un código nuevo.'], 429);
        }
        if (! Hash::check($data['code'], $guardado['hash'])) {
            $guardado['intentos']++;
            Cache::put($clave, $guardado, $guardado['expira']);
            return response()->json(['message' => 'Código inválido'], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return response()->json(['message' => 'El código venció o no es válido. Solicita uno nuevo.'], 422);
        }

        $user->update(['password' => $data['password'], 'must_change_password' => false]);
        $user->tokens()->delete(); // cierra cualquier sesión abierta
        Cache::forget($clave);

        return response()->json(['message' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('docente'));
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed|different:current_password',
        ]);

        $user = $request->user();
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta'], 422);
        }

        $user->update(['password' => $request->password, 'must_change_password' => false]);
        return response()->json(['message' => 'Contraseña actualizada']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada']);
    }
}