<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
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

// Catálogos administrables (Fase 3) — solo ADMIN.
Route::middleware(['auth', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/programas', Programas::class)->name('programas');
    Route::get('/periodos', Periodos::class)->name('periodos');
    Route::get('/tipos-documento', TiposDocumento::class)->name('tipos-documento');
    Route::get('/convenios', Convenios::class)->name('convenios');
});
