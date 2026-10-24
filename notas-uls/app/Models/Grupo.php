<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $fillable = ['nombre', 'ciclo_id', 'materia_id', 'docente_id'];

    public function ciclo()       { return $this->belongsTo(Ciclo::class); }
    public function materia()     { return $this->belongsTo(Materia::class); }
    public function docente()     { return $this->belongsTo(Docente::class); }
    public function estudiantes() { return $this->belongsToMany(Estudiante::class, 'grupo_estudiante'); }
    public function notas()       { return $this->hasMany(Nota::class); }
}
