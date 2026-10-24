<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    private function rules(): array
    {
        return [
            'nombre'     => 'required|string|max:100',
            'ciclo_id'   => 'required|exists:ciclos,id',
            'materia_id' => 'required|exists:materias,id',
            'docente_id' => 'required|exists:docentes,id',
        ];
    }

    public function index(Request $request)
    {
        return Grupo::with(['ciclo', 'materia', 'docente:id,nombres,apellidos'])
            ->withCount('estudiantes')
            ->when($request->ciclo_id, fn ($q, $v) => $q->where('ciclo_id', $v))
            ->when($request->docente_id, fn ($q, $v) => $q->where('docente_id', $v))
            ->orderBy('nombre')
            ->paginate($request->integer('per_page', 5));
    }

    public function store(Request $request)
    {
        $grupo = Grupo::create($request->validate($this->rules()));
        return response()->json($grupo->load(['ciclo', 'materia', 'docente']), 201);
    }

    public function show(Grupo $grupo)
    {
        return $grupo->load(['ciclo', 'materia', 'docente', 'estudiantes']);
    }

    public function update(Request $request, Grupo $grupo)
    {
        $grupo->update($request->validate($this->rules()));
        return $grupo->load(['ciclo', 'materia', 'docente']);
    }

    public function destroy(Grupo $grupo)
    {
        $grupo->delete();
        return response()->json(['message' => 'Grupo eliminado']);
    }

    // Reemplaza la lista de estudiantes del grupo
    public function asignarEstudiantes(Request $request, Grupo $grupo)
    {
        $data = $request->validate([
            'estudiantes'   => 'required|array',
            'estudiantes.*' => 'exists:estudiantes,id',
        ]);
        $grupo->estudiantes()->sync($data['estudiantes']);
        return $grupo->load('estudiantes');
    }
}
