<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * Panel operativo mínimo: bienvenida + contadores de la Fase 14
     * (`livewire:dashboard.panel-operativo`, autorizado server-side).
     */
    public function index(): Response
    {
        return response()->view('dashboard');
    }
}
