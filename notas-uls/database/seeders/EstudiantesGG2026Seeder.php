<?php

namespace Database\Seeders;

use App\Models\Ciclo;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Nota;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Borra TODOS los estudiantes, sus inscripciones y sus notas, y crea 30 estudiantes nuevos
 * (carnets GG2026001 a GG2026030): 10 al azar en cada uno de los grupos 01, 02 y 03,
 * con notas aleatorias (algunos aprobados y otros reprobados).
 *
 * Los grupos, ciclos, materias, docentes y usuarios NO se tocan.
 *
 * Uso: php artisan db:seed --class=EstudiantesGG2026Seeder
 */
class EstudiantesGG2026Seeder extends Seeder
{
    private const NOMBRES = [
        'Ana María', 'Luis Fernando', 'Sofía', 'Mateo', 'Valeria', 'Diego', 'Camila', 'Andrés', 'Lucía', 'Pablo',
        'Daniela', 'Jorge', 'Gabriela', 'Carlos', 'Fernanda', 'José Manuel', 'Paola', 'Ricardo', 'Karla', 'Miguel Ángel',
        'Alejandra', 'Roberto', 'Marcela', 'Kevin', 'Stephanie', 'Oscar', 'Rebeca', 'Eduardo', 'Michelle', 'Néstor',
    ];

    private const APELLIDOS = [
        'Hernández', 'Martínez', 'López', 'García', 'Rodríguez', 'Pérez', 'Ramírez', 'Flores', 'Gómez', 'Cruz',
        'Mejía', 'Guzmán', 'Alvarado', 'Rivas', 'Castillo', 'Portillo', 'Menjívar', 'Escobar', 'Quintanilla', 'Orellana',
        'Villalta', 'Cañas', 'Zelaya', 'Amaya', 'Argueta', 'Henríquez', 'Chávez', 'Sandoval', 'Barrera', 'Magaña',
    ];

    public function run(): void
    {
        $this->command->warn('Se borrarán TODOS los estudiantes y sus notas, y se crearán 30 nuevos.');
        if (! $this->command->confirm('¿Continuar?', false)) {
            $this->command->info('Operación cancelada. No se cambió nada.');
            return;
        }

        $grupos = $this->gruposDestino();

        DB::transaction(function () use ($grupos) {
            // 1) Limpieza
            Nota::query()->delete();
            DB::table('grupo_estudiante')->delete();
            Estudiante::query()->delete();

            // 2) 30 estudiantes: apellidos + nombres sin repetir
            $nombres = self::NOMBRES;
            $ap1 = self::APELLIDOS;
            $ap2 = self::APELLIDOS;
            shuffle($nombres);
            shuffle($ap1);
            shuffle($ap2);

            $estudiantes = [];
            for ($i = 0; $i < 30; $i++) {
                // evita "López López"
                if ($ap1[$i] === $ap2[$i]) {
                    $ap2[$i] = $ap2[($i + 1) % 30];
                }
                $estudiantes[] = Estudiante::create([
                    'nombres'   => $nombres[$i],
                    'apellidos' => $ap1[$i] . ' ' . $ap2[$i],
                    'carnet'    => 'GG2026' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                ]);
            }

            // 3) Reparto al azar: 10 por grupo
            shuffle($estudiantes);
            $resumen = [];

            foreach ($grupos as $g => $grupo) {
                $deGrupo = array_slice($estudiantes, $g * 10, 10);
                $grupo->estudiantes()->sync(array_map(fn ($e) => $e->id, $deGrupo));

                // Cuántos reprobados (al azar entre 2 y 5) y cuáles
                $reprobados = mt_rand(2, 5);
                $indicesReprobados = array_rand($deGrupo, $reprobados);
                $indicesReprobados = (array) $indicesReprobados;

                foreach ($deGrupo as $i => $e) {
                    [$n, $prom] = $this->generarNotas(! in_array($i, $indicesReprobados, true));
                    Nota::create([
                        'estudiante_id' => $e->id,
                        'grupo_id'      => $grupo->id,
                        'nota1'         => $n[0],
                        'nota2'         => $n[1],
                        'nota3'         => $n[2],
                        'promedio'      => $prom,
                        'estado'        => $prom >= 6 ? 'aprobado' : 'reprobado',
                        'fecha'         => now()->subMonths(mt_rand(0, 3))->toDateString(), // varios meses para el gráfico
                    ]);
                }

                $resumen[] = [$grupo->nombre, count($deGrupo), 10 - $reprobados, $reprobados];
            }

            $this->command->table(['Grupo', 'Estudiantes', 'Aprobados', 'Reprobados'], $resumen);
        });

        $this->command->info('Listo: 30 estudiantes (GG2026001 a GG2026030) con sus notas.');
    }

    /** Devuelve Grupo 01, 02 y 03; si alguno no existe, lo crea con los datos que haya. */
    private function gruposDestino(): array
    {
        $ciclo = Ciclo::orderByDesc('id')->first()
            ?? Ciclo::create(['nombre' => 'Ciclo 02-2026', 'fecha_inicio' => '2026-07-20', 'fecha_fin' => '2026-12-05']);
        $materias = Materia::orderBy('id')->get();
        $docente = Docente::orderBy('id')->first();

        $grupos = [];
        foreach (['Grupo 01', 'Grupo 02', 'Grupo 03'] as $i => $nombre) {
            $grupo = Grupo::where('nombre', $nombre)->orderBy('id')->first();

            if (! $grupo) {
                $materia = $materias->get($i) ?? $materias->first()
                    ?? Materia::create(['nombre' => 'Materia ' . ($i + 1)]);
                $grupo = Grupo::create([
                    'nombre'     => $nombre,
                    'ciclo_id'   => $ciclo->id,
                    'materia_id' => $materia->id,
                    'docente_id' => $docente?->id,
                ]);
            }
            $grupos[] = $grupo;
        }

        return $grupos;
    }

    /** @return array{0: float[], 1: float} notas 1-3 y promedio, coherentes con aprobar o reprobar */
    private function generarNotas(bool $aprueba): array
    {
        do {
            $base = $aprueba ? mt_rand(62, 98) / 10 : mt_rand(25, 57) / 10;
            $n = [];
            for ($k = 0; $k < 3; $k++) {
                $n[] = max(0, min(10, round($base + mt_rand(-15, 15) / 10, 1)));
            }
            $prom = round(array_sum($n) / 3, 2);
        } while (($prom >= 6) !== $aprueba);

        return [$n, $prom];
    }
}