<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Notas del grupo {{ $grupo->nombre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #313945; }
        h1 { font-size: 18px; color: #2D52A8; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 0 0 12px 0; font-weight: normal; }
        .info { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .info td { padding: 2px 4px; font-size: 10px; }
        table.notas { width: 100%; border-collapse: collapse; }
        table.notas th { background: #2D52A8; color: #fff; padding: 6px; font-size: 10px; text-align: left; }
        table.notas td { border-bottom: 1px solid #ddd; padding: 5px 6px; }
        .c { text-align: center; }
        .aprobado { color: #1f9d6a; font-weight: bold; }
        .reprobado { color: #EF5350; font-weight: bold; }
        .pie { margin-top: 14px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <h1>Universidad Luterana Salvadoreña</h1>
    <h2>Cuadro de notas - {{ $grupo->materia->nombre ?? '' }}</h2>

    <table class="info">
        <tr>
            <td><strong>Grupo:</strong> {{ $grupo->nombre }}</td>
            <td><strong>Ciclo:</strong> {{ $grupo->ciclo->nombre ?? '' }}</td>
        </tr>
        <tr>
            <td><strong>Docente:</strong> {{ ($grupo->docente->nombres ?? '') . ' ' . ($grupo->docente->apellidos ?? '') }}</td>
            <td><strong>Periodo:</strong>
                @if($grupo->ciclo)
                    {{ \Carbon\Carbon::parse($grupo->ciclo->fecha_inicio)->format('d/m/Y') }}
                    al
                    {{ \Carbon\Carbon::parse($grupo->ciclo->fecha_fin)->format('d/m/Y') }}
                @endif
            </td>
        </tr>
        <tr>
            <td><strong>Generado por:</strong> {{ $usuario }}</td>
            <td><strong>Fecha y hora:</strong> {{ $fecha }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Total de registros:</strong> {{ count($filas) }}</td>
        </tr>
    </table>

    <table class="notas">
        <thead>
            <tr>
                <th>#</th>
                <th>Carnet</th>
                <th>Estudiante</th>
                <th class="c">Nota 1</th>
                <th class="c">Nota 2</th>
                <th class="c">Nota 3</th>
                <th class="c">Promedio</th>
                <th class="c">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $i => $f)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $f['carnet'] }}</td>
                    <td>{{ $f['estudiante'] }}</td>
                    <td class="c">{{ $f['nota1'] }}</td>
                    <td class="c">{{ $f['nota2'] }}</td>
                    <td class="c">{{ $f['nota3'] }}</td>
                    <td class="c">{{ $f['promedio'] }}</td>
                    <td class="c {{ strtolower($f['estado']) }}">{{ $f['estado'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="c">Este grupo no tiene estudiantes.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pie">Aprobado con promedio de 6.0 o más.</div>
</body>
</html>
