<?php

namespace App\Livewire\Dashboard;

use App\Models\Inscripcion;
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
 * - Autorización reutilizada: `viewAny` de `Inscripcion` (la capacidad de
 *   lectura/listados existente, matriz §8.2) — los 4 roles autenticados y
 *   activos pasan; cualquier cuenta inactiva recibe 403 vía `Gate::before`,
 *   también cuando se fuerza el acceso a Livewire.
 * - Solo lectura: sin acciones que muten datos; sin información médica
 *   (RB-18) y sin cache/Redis/jobs.
 */
class PanelOperativo extends Component
{
    public function mount(): void
    {
        Gate::authorize('viewAny', Inscripcion::class);
    }

    public function render()
    {
        // Única fuente de los contadores: DashboardService (FC-9 / RB-20).
        return view('livewire.dashboard.panel-operativo', [
            'contadores' => app(DashboardService::class)->contadores(),
        ]);
    }
}
