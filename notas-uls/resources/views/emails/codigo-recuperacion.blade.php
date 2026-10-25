<x-mail::message>
# Hola, {{ $user->name }}

Recibimos una solicitud para restablecer tu contraseña en el **Sistema de Gestión de Constancias o Notas ULS**. Usa este código para confirmar tu cuenta:

<x-mail::panel>
# {{ $codigo }}
</x-mail::panel>

El código vence en {{ $minutos }} minutos. Si no fuiste tú quien lo solicitó, ignora este correo: tu contraseña no cambiará.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>