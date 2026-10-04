<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusInscripcionMateria;
use App\Models\Calificacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Models\MatriculaPeriodo;
use App\Services\CalificacionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class CalificacionAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroGrupo = '';
    public string $filtroMateria = '';
    public string $filtroEstatus = '';

    public ?Grupo $grupo = null;

    public bool $modalCalificacionAbierto = false;
    public ?Calificacion $calificacionEnEdicion = null;
    public float $valor = 0;
    public string $observacion = '';

    protected $listeners = ['calificacionActualizada'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('calificacion.crear');
        $this->grupo = $grupo;
    }

    public function calificacionActualizada(): void
    {
        $this->resetPage();
    }

    public function abrirModalCapturar(InscripcionMateria $inscripcion, Evaluacion $evaluacion): void
    {
        Gate::authorize('calificacion.crear');

        $existente = Calificacion::where('inscripcion_materia_id', $inscripcion->id)
            ->where('evaluacion_id', $evaluacion->id)
            ->first();

        if ($existente) {
            $this->calificacionEnEdicion = $existente;
            $this->valor = $existente->valor;
            $this->observacion = $existente->observacion ?? '';
        } else {
            $this->calificacionEnEdicion = null;
            $this->valor = 0;
            $this->observacion = '';
        }

        $this->dispatch('abrir-modal-calificacion', [
            'inscripcionId' => $inscripcion->id,
            'evaluacionId' => $evaluacion->id,
        ]);
    }

    public function cerrarModalCalificacion(): void
    {
        $this->calificacionEnEdicion = null;
        $this->valor = 0;
        $this->observacion = '';
        $this->resetErrorBag();
    }

    public function guardarCalificacion(): void
    {
        $this->validate([
            'valor' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d+(\.\d{1,2})?$/'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $service = app(\App\Services\CalificacionService::class);
            $inscripcion = \App\Models\InscripcionMateria::findOrFail($this->dispatch->get('inscripcionId') ?? 0);
            $evaluacion = \App\Models\Evaluacion::findOrFail($this->dispatch->get('evaluacionId') ?? 0);

            $service->capturar(
                $inscripcion,
                $evaluacion,
                $this->valor,
                $this->observacion,
                auth()->user()
            );

            session()->flash('success', 'Calificación capturada correctamente.');
            $this->dispatch('calificacionActualizada');
            $this->cerrarModalCalificacion();
        } catch (\App\Exceptions\CalificacionException $e) {
            $this->addError('valor', $e->getMessage());
        }
    }

    public function consolidar(InscripcionMateria $inscripcion): void
    {
        Gate::authorize('calificacion.consolidar', $inscripcion);

        try {
            $service = app(\App\Services\CalificacionService::class);
            $service->consolidar($inscripcion, auth()->user());
            session()->flash('success', 'Calificación final consolidada correctamente.');
        } catch (\App\Exceptions\CalificacionException $e) {
            $this->addError('consolidar', $e->getMessage());
        }
    }

    public function render()
    {
        $evaluaciones = $this->grupo
            ? \App\Models\Evaluacion::query()
                ->where('grupo_id', $this->grupo->id)
                ->where('estatus', '!=', 'BORRADOR')
                ->with(['calificaciones'])
                ->orderBy('fecha')
                ->get()
            : collect();

        $inscripciones = $this->grupo
            ? \App\Models\InscripcionMateria::query()
                ->where('grupo_id', $this->grupo->id)
                ->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO)
                ->with(['matriculaPeriodo.alumno', 'calificaciones'])
                ->orderBy('matricula_periodo_id')
                ->get()
            : collect();

        return view('livewire.academico.calificacion-admin', [
            'grupo' => $this->grupo,
            'evaluaciones' => $evaluaciones,
            'inscripciones' => $inscripciones,
        ]);
    }
}