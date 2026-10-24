<?php

namespace App\Http\Controllers;

use App\Models\Ciclo;
use Illuminate\Http\Request;

class CicloController extends Controller
{
    public function index(Request $request)
    {
        $q = Ciclo::orderByDesc('fecha_inicio');
        return $request->boolean('all') ? $q->get() : $q->paginate($request->integer('per_page', 5));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);
        return response()->json(Ciclo::create($data), 201);
    }

    public function show(Ciclo $ciclo) { return $ciclo; }

    public function update(Request $request, Ciclo $ciclo)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin'    => 'required|date|after:fecha_inicio',
        ]);
        $ciclo->update($data);
        return $ciclo;
    }

    public function destroy(Ciclo $ciclo)
    {
        $ciclo->delete();
        return response()->json(['message' => 'Ciclo eliminado']);
    }
}
