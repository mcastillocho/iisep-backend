<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');
Route::inertia('/admin', 'Admin/Index')->name('admin');
Route::inertia('/admin/oferta-academica', 'Admin/OfertaAcademica')->name('admin.oferta-academica');
