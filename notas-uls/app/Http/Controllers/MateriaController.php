<?php

namespace App\Http\Controllers;

use App\Models\Materia;
use Illuminate\Http\Request;

class MateriaController extends Controller
{
    public function index(Request $request)
    {
        $q = Materia::orderBy('nombre');
        return $request->boolean('all') ? $q->get() : $q->paginate($request->integer('per_page', 5));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nombre' => 'required|string|max:100']);
        return response()->json(Materia::create($data), 201);
    }

    public function show(Materia $materia) { return $materia; }

    public function update(Request $request, Materia $materia)
    {
        $materia->update($request->validate(['nombre' => 'required|string|max:100']));
        return $materia;
    }

    public function destroy(Materia $materia)
    {
        $materia->delete();
        return response()->json(['message' => 'Materia eliminada']);
    }
}
