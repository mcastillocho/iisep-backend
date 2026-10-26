<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AcademicOfferController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\ClassroomController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ScheduledCourseController;
use App\Http\Controllers\Admin\WorkshopController;
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
    Route::get('/matriculas', [ScheduledCourseController::class, 'index'])->name('admin.matriculas');
    Route::get('/alumnos', [ScheduledCourseController::class, 'index'])->name('admin.alumnos');
    Route::post('/alumnos', [ScheduledCourseController::class, 'storeStudent'])->name('admin.students.store');
    Route::put('/alumnos/{student}', [ScheduledCourseController::class, 'updateStudent'])->name('admin.students.update');
    Route::post('/cursos-aperturados', [ScheduledCourseController::class, 'store'])->name('admin.scheduled-courses.store');
    Route::put('/cursos-aperturados/{scheduledCourse}', [ScheduledCourseController::class, 'update'])->name('admin.scheduled-courses.update');
    Route::delete('/cursos-aperturados/{scheduledCourse}', [ScheduledCourseController::class, 'destroy'])->name('admin.scheduled-courses.destroy');
    Route::post('/matriculas', [ScheduledCourseController::class, 'storeEnrollment'])->name('admin.enrollments.store');
    Route::put('/matriculas/{enrollment}/status', [ScheduledCourseController::class, 'updateEnrollmentStatus'])->name('admin.enrollments.status');
    Route::delete('/matriculas/{enrollment}', [ScheduledCourseController::class, 'destroyEnrollment'])->name('admin.enrollments.destroy');
    Route::get('/estudiantes/buscar-dni', [ScheduledCourseController::class, 'searchStudentByDni'])->name('admin.students.search');

    // Oferta Académica, Carreras, Módulos y Talleres
    Route::get('/oferta-academica', [AcademicOfferController::class, 'index'])->name('admin.oferta-academica');
    Route::post('/carreras', [CareerController::class, 'store'])->name('admin.careers.store');
    Route::put('/carreras/{career}', [CareerController::class, 'update'])->name('admin.careers.update');
    Route::delete('/carreras/{career}', [CareerController::class, 'destroy'])->name('admin.careers.destroy');
    Route::post('/modulos', [ModuleController::class, 'store'])->name('admin.modules.store');
    Route::put('/modulos/{module}', [ModuleController::class, 'update'])->name('admin.modules.update');
    Route::delete('/modulos/{module}', [ModuleController::class, 'destroy'])->name('admin.modules.destroy');
    Route::post('/talleres', [WorkshopController::class, 'store'])->name('admin.workshops.store');
    Route::put('/talleres/{course}', [WorkshopController::class, 'update'])->name('admin.workshops.update');
    Route::delete('/talleres/{course}', [WorkshopController::class, 'destroy'])->name('admin.workshops.destroy');

    // Sedes y Aulas
    Route::get('/sedes-aulas', [BranchController::class, 'index'])->name('admin.sedes-aulas');
    Route::post('/sedes', [BranchController::class, 'store'])->name('admin.sedes.store');
    Route::put('/sedes/{branch}', [BranchController::class, 'update'])->name('admin.sedes.update');
    Route::post('/aulas', [ClassroomController::class, 'store'])->name('admin.classrooms.store');
    Route::put('/aulas/{classroom}', [ClassroomController::class, 'update'])->name('admin.classrooms.update');
    Route::delete('/aulas/{classroom}', [ClassroomController::class, 'destroy'])->name('admin.classrooms.destroy');

    Route::inertia('/asistencias', 'Admin/Asistencias')->name('admin.asistencias');
});
