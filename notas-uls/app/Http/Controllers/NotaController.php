<?php

namespace App\Http\Controllers;

use App\Exports\NotasGrupoExport;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Nota;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class NotaController extends Controller
{
    private const NOTA_MINIMA = 6.0;

    // El docente solo puede tocar sus propios grupos
    private function autorizar(Request $request, Grupo $grupo): void
    {
        $user = $request->user();
        if ($user->role === 'docente' && $grupo->docente_id !== optional($user->docente)->id) {
            abort(403, 'Este grupo no te pertenece');
        }
    }

    // Grupos visibles: admin ve todos, docente solo los suyos (filtrable por ciclo)
    public function misGrupos(Request $request)
    {
        $user = $request->user();

        return Grupo::with(['ciclo', 'materia'])
            ->withCount('estudiantes')
            ->when($user->role === 'docente', fn ($q) => $q->where('docente_id', optional($user->docente)->id))
            ->when($request->ciclo_id, fn ($q, $v) => $q->where('ciclo_id', $v))
            ->orderBy('nombre')
            ->get();
    }

    // Estudiantes del grupo con sus notas (paginado de 5)
    public function index(Request $request, Grupo $grupo)
    {
        $this->autorizar($request, $grupo);

        $notas = $grupo->notas()->get()->keyBy('estudiante_id');

        $pagina = $grupo->estudiantes()
            ->when($request->buscar, fn ($q, $b) => $q->where(function ($q) use ($b) {
                $q->where('nombres', 'like', "%$b%")
                  ->orWhere('apellidos', 'like', "%$b%")
                  ->orWhere('carnet', 'like', "%$b%");
            }))
            ->orderBy('apellidos')
            ->paginate($request->integer('per_page', 5));

        $pagina->getCollection()->transform(function ($e) use ($notas) {
            $n = $notas->get($e->id);
            return [
                'estudiante_id' => $e->id,
                'carnet'        => $e->carnet,
                'nombre'        => $e->apellidos . ', ' . $e->nombres,
                'nota1'         => $n?->nota1,
                'nota2'         => $n?->nota2,
                'nota3'         => $n?->nota3,
                'promedio'      => $n?->promedio,
                'estado'        => $n?->estado,
            ];
        });

        return $pagina;
    }

    // Registrar o actualizar la nota de un estudiante en el grupo
    public function guardar(Request $request, Grupo $grupo, Estudiante $estudiante)
    {
        $this->autorizar($request, $grupo);

        if (!$grupo->estudiantes()->where('estudiantes.id', $estudiante->id)->exists()) {
            return response()->json(['message' => 'El estudiante no pertenece a este grupo'], 404);
        }

        $data = $request->validate([
            'nota1' => 'required|numeric|min:0|max:10',
            'nota2' => 'required|numeric|min:0|max:10',
            'nota3' => 'required|numeric|min:0|max:10',
            'fecha' => 'nullable|date',
        ]);

        $promedio = round(($data['nota1'] + $data['nota2'] + $data['nota3']) / 3, 2);

        $nota = Nota::updateOrCreate(
            ['estudiante_id' => $estudiante->id, 'grupo_id' => $grupo->id],
            [
                'nota1'    => $data['nota1'],
                'nota2'    => $data['nota2'],
                'nota3'    => $data['nota3'],
                'promedio' => $promedio,
                'estado'   => $promedio >= self::NOTA_MINIMA ? 'aprobado' : 'reprobado',
                'fecha'    => $data['fecha'] ?? now()->toDateString(),
            ]
        );

        return response()->json(['message' => 'Nota guardada', 'nota' => $nota]);
    }

    // Filas para PDF y Excel
    private function filas(Grupo $grupo): array
    {
        $notas = $grupo->notas()->get()->keyBy('estudiante_id');

        return $grupo->estudiantes()->orderBy('apellidos')->get()->map(function ($e) use ($notas) {
            $n = $notas->get($e->id);
            return [
                'carnet'     => $e->carnet,
                'estudiante' => $e->apellidos . ', ' . $e->nombres,
                'nota1'      => $n?->nota1 ?? '-',
                'nota2'      => $n?->nota2 ?? '-',
                'nota3'      => $n?->nota3 ?? '-',
                'promedio'   => $n?->promedio ?? '-',
                'estado'     => $n ? ucfirst($n->estado) : 'Sin nota',
            ];
        })->all();
    }

    public function pdf(Request $request, Grupo $grupo)
    {
        $this->autorizar($request, $grupo);
        $grupo->load(['ciclo', 'materia', 'docente']);

        return Pdf::loadView('pdf.notas-grupo', [
                'grupo'   => $grupo,
                'filas'   => $this->filas($grupo),
                'usuario' => $request->user()->name,
                'fecha'   => now()->format('d/m/Y H:i'),
            ])
            ->setPaper('letter')
            ->stream("notas-grupo-{$grupo->id}.pdf"); // se muestra en línea (visor)
    }

        public function excel(Request $request, Grupo $grupo)
    {
        $this->autorizar($request, $grupo);
        $grupo->load(['ciclo', 'materia']);

        $csv = $request->query('formato') === 'csv';

        return Excel::download(
            new NotasGrupoExport($grupo, $this->filas($grupo), $request->user()->name),
            "notas-grupo-{$grupo->id}." . ($csv ? 'csv' : 'xlsx'),
            $csv ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX
        );
    }

    // Gráfico: aprobados vs reprobados de un mes
    public function grafico(Request $request)
    {
        $data = $request->validate([
            'mes'      => 'required|integer|between:0,12', // 0 = todos los meses del año
            'anio'     => 'nullable|integer|min:2000|max:2100',
            'grupo_id' => 'nullable|exists:grupos,id',
        ]);

        $user = $request->user();

        $q = Nota::query()
            ->whereYear('fecha', $data['anio'] ?? now()->year)
            ->when($data['mes'] > 0, fn ($q) => $q->whereMonth('fecha', $data['mes']))
            ->when($user->role === 'docente', fn ($q) =>
                $q->whereHas('grupo', fn ($g) => $g->where('docente_id', optional($user->docente)->id)))
            ->when($data['grupo_id'] ?? null, fn ($q, $v) => $q->where('grupo_id', $v));

        return response()->json([
            'mes'        => (int) $data['mes'],
            'anio'       => (int) ($data['anio'] ?? now()->year),
            'aprobados'  => (clone $q)->where('estado', 'aprobado')->count(),
            'reprobados' => (clone $q)->where('estado', 'reprobado')->count(),
        ]);
    }
}