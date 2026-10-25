<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/admin', 'Admin/Index')->name('admin');
Route::inertia('/admin/matriculas', 'Admin/Matriculas')->name('admin.matriculas');
Route::inertia('/admin/oferta-academica', 'Admin/OfertaAcademica')->name('admin.oferta-academica');
Route::inertia('/admin/sedes-aulas', 'Admin/SedesAulas')->name('admin.sedes-aulas');
Route::inertia('/admin/asistencias', 'Admin/Asistencias')->name('admin.asistencias');
