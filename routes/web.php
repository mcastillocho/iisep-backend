<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

// Página Principal Pública
Route::inertia('/', 'Welcome')->name('home');

// Rutas de Autenticación
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Rutas Protegidas del Panel Administrativo (Requiere inicio de sesión de Staff)
Route::middleware(['auth'])->prefix('admin')->group(function (): void {
    Route::get('/', [BranchController::class, 'dashboard'])->name('admin');
    Route::inertia('/matriculas', 'Admin/Matriculas')->name('admin.matriculas');
    Route::inertia('/oferta-academica', 'Admin/OfertaAcademica')->name('admin.oferta-academica');

    // Sedes y Aulas
    Route::get('/sedes-aulas', [BranchController::class, 'index'])->name('admin.sedes-aulas');
    Route::post('/sedes', [BranchController::class, 'store'])->name('admin.sedes.store');
    Route::post('/aulas', [ClassroomController::class, 'store'])->name('admin.classrooms.store');
    Route::put('/aulas/{classroom}', [ClassroomController::class, 'update'])->name('admin.classrooms.update');
    Route::delete('/aulas/{classroom}', [ClassroomController::class, 'destroy'])->name('admin.classrooms.destroy');

    Route::inertia('/asistencias', 'Admin/Asistencias')->name('admin.asistencias');
});
