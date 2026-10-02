<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Livewire\Alumnos\FormAlumno;
use App\Livewire\Alumnos\FormContactos;
use App\Livewire\Alumnos\FormInfoMedica;
use App\Livewire\Alumnos\ListaAlumnos;
use App\Livewire\Catalogos\Convenios;
use App\Livewire\Catalogos\Periodos;
use App\Livewire\Catalogos\Programas;
use App\Livewire\Catalogos\TiposDocumento;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

// Rutas de alumnos / expediente (Fase 4) — solo usuarios autenticados;
// la autorización fina (policy/gate) vive en cada componente Livewire.
Route::middleware('auth')->prefix('alumnos')->name('alumnos.')->group(function () {
    Route::get('/', ListaAlumnos::class)->name('lista');
    Route::get('/nuevo', FormAlumno::class)->name('nuevo');
    Route::get('/{alumno}', FormAlumno::class)->name('ver');
    Route::get('/{alumno}/contactos', FormContactos::class)->name('contactos');
    Route::get('/{alumno}/info-medica', FormInfoMedica::class)->name('info-medica');
});

// Catálogos administrables (Fase 3) — solo ADMIN.
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/programas', Programas::class)->name('programas');
    Route::get('/periodos', Periodos::class)->name('periodos');
    Route::get('/tipos-documento', TiposDocumento::class)->name('tipos-documento');
    Route::get('/convenios', Convenios::class)->name('convenios');
});
