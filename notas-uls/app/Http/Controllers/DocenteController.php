<?php

namespace App\Http\Controllers;

use App\Mail\DocenteCredenciales;
use App\Models\Docente;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocenteController extends Controller
{
    public function index(Request $request)
    {
        return Docente::with('user:id,email,estado')
            ->when($request->nombre, function ($q, $nombre) {
                $q->where(function ($q) use ($nombre) {
                    $q->where('nombres', 'like', "%$nombre%")
                      ->orWhere('apellidos', 'like', "%$nombre%")
                      ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%$nombre%"));
                });
            })
            ->when($request->estado, fn ($q, $estado) =>
                $q->whereHas('user', fn ($u) => $u->where('estado', $estado)))
            ->orderBy('apellidos')
            ->paginate($request->integer('per_page', 5));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombres'      => 'required|string|max:100',
            'apellidos'    => 'required|string|max:100',
            'email'        => 'required|email|unique:users,email',
            'telefono'     => 'nullable|string|max:20',
            'especialidad' => 'nullable|string|max:100',
        ]);

        $tempPassword = Str::password(10, true, true, false);

        $docente = DB::transaction(function () use ($data, $tempPassword) {
            $user = User::create([
                'name'                 => $data['nombres'] . ' ' . $data['apellidos'],
                'email'                => $data['email'],
                'password'             => $tempPassword,
                'role'                 => 'docente',
                'estado'               => 'habilitado',
                'must_change_password' => true,
            ]);

            return Docente::create([
                'user_id'      => $user->id,
                'nombres'      => $data['nombres'],
                'apellidos'    => $data['apellidos'],
                'telefono'     => $data['telefono'] ?? null,
                'especialidad' => $data['especialidad'] ?? null,
            ]);
        });

        $enviado = $this->enviarCorreo($docente, $data['email'], $tempPassword);

        return response()->json([
            'message'        => 'Docente registrado',
            'correo_enviado' => $enviado,
            'docente'        => $docente->load('user:id,email,estado'),
        ], 201);
    }

    public function show(Docente $docente)
    {
        return $docente->load('user:id,email,estado');
    }

    public function update(Request $request, Docente $docente)
    {
        $data = $request->validate([
            'nombres'      => 'required|string|max:100',
            'apellidos'    => 'required|string|max:100',
            'email'        => ['required', 'email', Rule::unique('users', 'email')->ignore($docente->user_id)],
            'telefono'     => 'nullable|string|max:20',
            'especialidad' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($docente, $data) {
            $docente->update([
                'nombres'      => $data['nombres'],
                'apellidos'    => $data['apellidos'],
                'telefono'     => $data['telefono'] ?? null,
                'especialidad' => $data['especialidad'] ?? null,
            ]);
            $docente->user->update([
                'name'  => $data['nombres'] . ' ' . $data['apellidos'],
                'email' => $data['email'],
            ]);
        });

        return response()->json([
            'message' => 'Docente actualizado',
            'docente' => $docente->fresh()->load('user:id,email,estado'),
        ]);
    }

    public function destroy(Docente $docente)
    {
        if ($docente->grupos()->exists()) {
            return response()->json([
                'message' => 'El docente tiene grupos asignados. Inhabilítalo en lugar de eliminarlo.',
            ], 409);
        }

        $docente->user->delete(); // borra también el docente (cascade)

        return response()->json(['message' => 'Docente eliminado']);
    }

    public function toggleEstado(Docente $docente)
    {
        $user = $docente->user;
        $user->estado = $user->estado === 'habilitado' ? 'inhabilitado' : 'habilitado';
        $user->save();

        if ($user->estado === 'inhabilitado') {
            $user->tokens()->delete(); // cierra sus sesiones activas
        }

        return response()->json(['message' => "Docente {$user->estado}", 'estado' => $user->estado]);
    }

    public function reenviarCredenciales(Docente $docente)
    {
        $tempPassword = Str::password(10, true, true, false);

        $docente->user->update([
            'password'             => $tempPassword,
            'must_change_password' => true,
        ]);
        $docente->user->tokens()->delete();

        $enviado = $this->enviarCorreo($docente, $docente->user->email, $tempPassword);

        return response()->json([
            'message'        => $enviado ? 'Correo enviado' : 'No se pudo enviar el correo',
            'correo_enviado' => $enviado,
        ], $enviado ? 200 : 502);
    }

    private function enviarCorreo(Docente $docente, string $email, string $password): bool
    {
        try {
            Mail::to($email)->send(new DocenteCredenciales($docente, $email, $password));
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
    }
}
