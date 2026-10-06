<?php

namespace App\Livewire\Docentes;

use App\Enums\EstatusInscripcionMateria;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal de docente — detalle de un grupo propio (2.0-15).
 *
 * Autorización doble en `mount`: `portal_docente.acceder` (rol DOCENTE) y
 * `GrupoPolicy::verComoDocente` (el grupo debe estar asignado a ese docente).
 * La captura se reutiliza desde los componentes existentes (`EvaluacionAdmin`,
 * `CalificacionAdmin`, `AsistenciaAdmin`, `ActaAdmin`) acotados al grupo.
 */
#[Layout('layouts.app')]
class DocenteGrupo extends Component
{
    public Grupo $grupo;

    public function mount(Grupo $grupo): void
    {
        Gate::authorize('portal_docente.acceder');
        Gate::authorize('verComoDocente', $grupo);

        $this->grupo = $grupo;
    }

    public function render()
    {
        $this->grupo->loadMissing(['materia', 'periodo', 'aula', 'horarios', 'docente']);

        $inscripciones = InscripcionMateria::where('grupo_id', $this->grupo->id)
            ->where('estatus', EstatusInscripcionMateria::CURSANDO->value)
            ->with(['matriculaPeriodo.alumno'])
            ->orderBy('matricula_periodo_id')
            ->get();

        return view('livewire.docentes.docente-grupo', [
            'inscripciones' => $inscripciones,
        ]);
    }
}
