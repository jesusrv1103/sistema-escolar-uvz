<?php

namespace App\Livewire\Alumnos;

use App\Models\MatriculaPeriodo;
use App\Services\HistorialAcademicoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de alumno — inicio (2.0-16).
 *
 * Identidad SIEMPRE desde `auth()->user()->alumno` (aislamiento C1): la ruta
 * no lleva `{alumno}` ni parámetros de consulta, así que una cuenta no puede
 * mirar el expediente de otra.
 */
#[Layout('layouts.app')]
class AlumnoInicio extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_alumno.acceder');
    }

    public function render()
    {
        $alumno = auth()->user()->alumno;

        $matricula = $alumno
            ? MatriculaPeriodo::where('alumno_id', $alumno->id)
                ->activas()
                ->with(['periodo', 'programa', 'planVersion'])
                ->orderByDesc('periodo_id')
                ->first()
            : null;

        $resumen = null;

        if ($alumno) {
            $historial = app(HistorialAcademicoService::class);
            $resumen = [
                'promedio' => $historial->calcularPromedio($alumno),
                'creditos_aprobados' => $historial->creditosAprobados($alumno),
                'creditos_totales' => $historial->creditosTotalesPlan($alumno),
                'porcentaje_avance' => $historial->porcentajeAvance($alumno),
            ];
        }

        return view('livewire.alumnos.alumno-inicio', [
            'alumno' => $alumno,
            'matricula' => $matricula,
            'resumen' => $resumen,
        ]);
    }
}
