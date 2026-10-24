<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class NotasGrupoExport implements FromArray, ShouldAutoSize, WithCustomCsvSettings
{
    public function __construct(
        private $grupo,
        private array $filas,
        private string $usuario
    ) {}

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'use_bom'   => true,
        ];
    }

    public function array(): array
    {
        $ciclo = $this->grupo->ciclo;
        $rango = $ciclo
            ? \Carbon\Carbon::parse($ciclo->fecha_inicio)->format('d/m/Y') . ' al ' . \Carbon\Carbon::parse($ciclo->fecha_fin)->format('d/m/Y')
            : '';

        $datos = [
            ['Universidad Luterana Salvadoreña'],
            ['Grupo', $this->grupo->nombre, 'Materia', $this->grupo->materia->nombre ?? ''],
            ['Rango de fechas', $rango],
            ['Generado por', $this->usuario],
            ['Fecha y hora', now()->format('d/m/Y H:i')],
            ['Total de registros', count($this->filas)],
            [],
            ['Carnet', 'Estudiante', 'Nota 1', 'Nota 2', 'Nota 3', 'Promedio', 'Estado'],
        ];

        foreach ($this->filas as $f) {
            $datos[] = [$f['carnet'], $f['estudiante'], $f['nota1'], $f['nota2'], $f['nota3'], $f['promedio'], $f['estado']];
        }

        return $datos;
    }
}
