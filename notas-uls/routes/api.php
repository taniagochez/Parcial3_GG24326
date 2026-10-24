<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CicloController;
use App\Http\Controllers\DocenteController;
use App\Http\Controllers\EstudianteController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\NotaController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'abilities:pre-2fa'])
    ->post('/2fa/verify', [AuthController::class, 'verify2fa']);

Route::middleware(['auth:sanctum', 'abilities:access'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Solo administrador
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('docentes', DocenteController::class);
        Route::patch('docentes/{docente}/estado', [DocenteController::class, 'toggleEstado']);
        Route::post('docentes/{docente}/reenviar', [DocenteController::class, 'reenviarCredenciales']);

        Route::apiResource('ciclos', CicloController::class);
        Route::apiResource('materias', MateriaController::class);
        Route::apiResource('estudiantes', EstudianteController::class);
        Route::apiResource('grupos', GrupoController::class);
        Route::post('grupos/{grupo}/estudiantes', [GrupoController::class, 'asignarEstudiantes']);
    });

    // Admin y docente
    Route::middleware('role:admin,docente')->group(function () {
        Route::get('mis-grupos', [NotaController::class, 'misGrupos']);
        Route::get('grupos/{grupo}/notas/pdf', [NotaController::class, 'pdf']);
        Route::get('grupos/{grupo}/notas/excel', [NotaController::class, 'excel']);
        Route::get('grupos/{grupo}/notas', [NotaController::class, 'index']);
        Route::put('grupos/{grupo}/notas/{estudiante}', [NotaController::class, 'guardar']);
        Route::get('grafico', [NotaController::class, 'grafico']);
    });
});
