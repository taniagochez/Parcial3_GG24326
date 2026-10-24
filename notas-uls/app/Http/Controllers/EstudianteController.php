<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EstudianteController extends Controller
{
    public function index(Request $request)
    {
        return Estudiante::query()
            ->when($request->buscar, fn ($q, $b) => $q->where(function ($q) use ($b) {
                $q->where('nombres', 'like', "%$b%")
                  ->orWhere('apellidos', 'like', "%$b%")
                  ->orWhere('carnet', 'like', "%$b%");
            }))
            ->orderBy('apellidos')
            ->paginate($request->integer('per_page', 5));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombres'   => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'carnet'    => 'required|string|max:20|unique:estudiantes,carnet',
        ]);
        return response()->json(Estudiante::create($data), 201);
    }

    public function show(Estudiante $estudiante) { return $estudiante; }

    public function update(Request $request, Estudiante $estudiante)
    {
        $data = $request->validate([
            'nombres'   => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'carnet'    => ['required', 'string', 'max:20', Rule::unique('estudiantes', 'carnet')->ignore($estudiante->id)],
        ]);
        $estudiante->update($data);
        return $estudiante;
    }

    public function destroy(Estudiante $estudiante)
    {
        $estudiante->delete();
        return response()->json(['message' => 'Estudiante eliminado']);
    }
}
