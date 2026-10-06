<?php

namespace App\Livewire\Dashboard;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel operativo del dashboard (Fase 14, FC-9 / RB-20).
 *
 * - Muestra como máximo los 4 indicadores aprobados, cada uno calculado
 *   EXCLUSIVAMENTE por `DashboardService` (sin queries duplicadas en la
 *   vista): tarjetas simples y responsive, sin gráficas, porcentajes,
 *   tendencias ni BI.
 * - Autorización: `inscripcion.ver` (lectura de operación, matriz §8.2;
 *   ADMISIONES, ADMINISTRACION y CONTROL_ESCOLAR) — con 2.0-15 el panel deja
 *   de estar abierto a cualquier autenticado: DOCENTE/ALUMNO reciben 403, y
 *   una cuenta inactiva también vía `Gate::before`, también cuando se fuerza
 *   el acceso a Livewire.
 * - Solo lectura: sin acciones que muten datos; sin información médica
 *   (RB-18) y sin cache/Redis/jobs.
 */
class PanelOperativo extends Component
{
    public function mount(): void
    {
        // Aislamiento de portales (2.0-15): el panel operativo es lectura de
        // operación (`inscripcion.ver`); DOCENTE/ALUMNO reciben 403.
        Gate::authorize('inscripcion.ver');
    }

    public function render()
    {
        // Única fuente de los contadores: DashboardService (FC-9 / RB-20).
        return view('livewire.dashboard.panel-operativo', [
            'contadores' => app(DashboardService::class)->contadores(),
        ]);
    }
}
