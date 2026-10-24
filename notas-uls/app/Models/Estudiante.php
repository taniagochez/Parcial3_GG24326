<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $fillable = ['nombres', 'apellidos', 'carnet'];

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'grupo_estudiante');
    }
}
