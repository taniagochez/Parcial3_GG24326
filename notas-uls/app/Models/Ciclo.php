<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciclo extends Model
{
    protected $fillable = ['nombre', 'fecha_inicio', 'fecha_fin'];

    public function grupos()
    {
        return $this->hasMany(Grupo::class);
    }
}
