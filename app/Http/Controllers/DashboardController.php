<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class DashboardController extends Controller
{
    /**
     * Panel operativo mínimo (los contadores llegan en la Fase 14).
     */
    public function index(): Response
    {
        return response()->view('dashboard');
    }
}
