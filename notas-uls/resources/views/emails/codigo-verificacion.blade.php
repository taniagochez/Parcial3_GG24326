<x-mail::message>
# Hola, {{ $user->name }}

Usa este código para completar tu primer inicio de sesión en el **Sistema de Gestión de Constancias o Notas ULS**:

<x-mail::panel>
# {{ $codigo }}
</x-mail::panel>

El código vence en {{ $minutos }} minutos. Si no fuiste tú quien intentó ingresar, ignora este correo y no compartas el código con nadie.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>