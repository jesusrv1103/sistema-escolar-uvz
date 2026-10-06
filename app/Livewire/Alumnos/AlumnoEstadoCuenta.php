<?php

namespace App\Livewire\Alumnos;

use App\Services\EstadoCuentaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de alumno — estado de cuenta propio (2.0-16).
 *
 * Misma fórmula que la vista administrativa, vía `EstadoCuentaService`
 * (sólo lectura; sin cobranza, intereses ni cargos nuevos: §5/J).
 * La consulta siempre está acotada al alumno de la cuenta.
 */
#[Layout('layouts.app')]
class AlumnoEstadoCuenta extends Component
{
    public function mount(): void
    {
        Gate::authorize('portal_alumno.acceder');
    }

    public function render()
    {
        $alumno = auth()->user()->alumno;
        $estado = $alumno ? app(EstadoCuentaService::class)->resumen($alumno) : null;

        return view('livewire.alumnos.alumno-estado-cuenta', [
            'alumno' => $alumno,
            'estado' => $estado,
        ]);
    }
}
