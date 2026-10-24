<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    protected $fillable = ['estudiante_id', 'grupo_id', 'nota1', 'nota2', 'nota3', 'promedio', 'estado', 'fecha'];

    public function estudiante() { return $this->belongsTo(Estudiante::class); }
    public function grupo()      { return $this->belongsTo(Grupo::class); }
}
