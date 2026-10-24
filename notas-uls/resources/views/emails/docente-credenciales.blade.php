<x-mail::message>
# Bienvenido(a), {{ $docente->nombres }} {{ $docente->apellidos }}

Se creó tu cuenta en el **Sistema de Gestión de Constancias o Notas ULS**.

<x-mail::panel>
**Correo:** {{ $email }}

**Contraseña temporal:** {{ $password }}

**Teléfono:** {{ $docente->telefono ?? 'No registrado' }}

**Especialidad:** {{ $docente->especialidad ?? 'No registrada' }}
</x-mail::panel>

Por seguridad, el sistema te pedirá cambiar esta contraseña en tu primer ingreso.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
