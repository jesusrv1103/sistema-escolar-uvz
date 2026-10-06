<?php

namespace App\Livewire\Alumnos;

use App\Services\HistorialAcademicoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de alumno — kardex / historial académico propio (2.0-16).
 *
 * Sólo lectura y sólo del alumno de la cuenta: la fuente es
 * `HistorialAcademicoService` (misma que la pantalla administrativa y los
 * formatos), sin generación de PDF (esa vía exige `formato_academico.generar`).
 */
#[Layout('layouts.app')]
class AlumnoKardex extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_alumno.acceder');
    }

    public function render()
    {
        $alumno = auth()->user()->alumno;

        $historial = $alumno ? app(HistorialAcademicoService::class) : null;
        $filas = $alumno ? $historial->obtenerHistorial($alumno) : collect();

        $resumen = $alumno ? [
            'promedio' => $historial->calcularPromedio($alumno),
            'creditos_aprobados' => $historial->creditosAprobados($alumno),
            'creditos_cursados' => $historial->creditosCursados($alumno),
            'creditos_totales' => $historial->creditosTotalesPlan($alumno),
            'porcentaje_avance' => $historial->porcentajeAvance($alumno),
        ] : null;

        return view('livewire.alumnos.alumno-kardex', [
            'alumno' => $alumno,
            'filas' => $filas,
            'resumen' => $resumen,
        ]);
    }
}
