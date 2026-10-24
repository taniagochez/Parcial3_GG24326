<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grupo_id')->constrained()->cascadeOnDelete();
            $table->decimal('nota1', 4, 2)->default(0);
            $table->decimal('nota2', 4, 2)->default(0);
            $table->decimal('nota3', 4, 2)->default(0);
            $table->decimal('promedio', 4, 2)->default(0);
            $table->enum('estado', ['aprobado', 'reprobado'])->default('reprobado');
            $table->date('fecha');
            $table->timestamps();
            $table->unique(['estudiante_id', 'grupo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas');
    }
};
