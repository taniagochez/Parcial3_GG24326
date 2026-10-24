<?php
 
namespace Database\Seeders;
 
use App\Models\Ciclo;
use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Nota;
use App\Models\User;
use Illuminate\Database\Seeder;
 
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $ciclo = Ciclo::create(['nombre' => 'Ciclo 02-2026', 'fecha_inicio' => '2026-07-20', 'fecha_fin' => '2026-12-05']);
 
        $materias = collect(['Programación Web', 'Base de Datos', 'Matemática Discreta'])
            ->map(fn ($n) => Materia::create(['nombre' => $n]));
 
        $user = User::create([
            'name' => 'Carlos Pérez', 'email' => 'docente@uls.edu.sv', 'password' => 'Docente123*',
            'role' => 'docente', 'must_change_password' => false,
        ]);
        $docente = Docente::create([
            'user_id' => $user->id, 'nombres' => 'Carlos', 'apellidos' => 'Pérez',
            'telefono' => '70001111', 'especialidad' => 'Informática',
        ]);
 
        $nombres = ['Ana','Luis','Sofía','Mateo','Valeria','Diego','Camila','Andrés','Lucía','Pablo','Daniela','Jorge'];
        $estudiantes = collect($nombres)->map(fn ($n, $i) => Estudiante::create([
            'nombres' => $n, 'apellidos' => 'Demo ' . ($i + 1), 'carnet' => 'GG' . (24326 + $i),
        ]));
 
        foreach ($materias as $i => $materia) {
            $grupo = Grupo::create([
                'nombre' => 'Grupo 0' . ($i + 1), 'ciclo_id' => $ciclo->id,
                'materia_id' => $materia->id, 'docente_id' => $docente->id,
            ]);
            $grupo->estudiantes()->sync($estudiantes->pluck('id'));
 
            foreach ($estudiantes as $e) {
                $n = [rand(40, 100) / 10, rand(40, 100) / 10, rand(40, 100) / 10];
                $prom = round(array_sum($n) / 3, 2);
                Nota::create([
                    'estudiante_id' => $e->id, 'grupo_id' => $grupo->id,
                    'nota1' => $n[0], 'nota2' => $n[1], 'nota3' => $n[2],
                    'promedio' => $prom, 'estado' => $prom >= 6 ? 'aprobado' : 'reprobado',
                    'fecha' => now()->subMonths(rand(0, 3))->toDateString(), // varios meses para el gráfico
                ]);
            }
        }
    }
}
 