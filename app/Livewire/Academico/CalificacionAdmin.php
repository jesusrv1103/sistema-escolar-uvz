<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusInscripcionMateria;
use App\Livewire\Concerns\AutorizaGrupoDocente;
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
    use AutorizaGrupoDocente, WithPagination;

    public string $busqueda = '';
    public string $filtroGrupo = '';
    public string $filtroMateria = '';
    public string $filtroEstatus = '';

    public ?Grupo $grupo = null;

    public bool $modalCalificacionAbierto = false;
    public ?Calificacion $calificacionEnEdicion = null;
    public ?InscripcionMateria $inscripcionCaptura = null;
    public ?Evaluacion $evaluacionCaptura = null;
    public float $valor = 0;
    public string $observacion = '';

    protected $listeners = ['calificacionActualizada'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('calificacion.crear');

        // Portal docente (2.0-15): el DOCENTE sólo entra por un grupo propio.
        $this->autorizarGrupoSiDocente($grupo);
        // Un Grupo vacío (resolución por contenedor sin id de ruta) no es una
        // selección: se normaliza a null para no consultar con `grupo_id null`.
        $this->grupo = $grupo?->exists ? $grupo : null;
    }

    public function calificacionActualizada(): void
    {
        $this->resetPage();
    }

    /**
     * Selección de grupo desde el filtro.
     *
     * No puede ser `wire:model`: `grupo` es un modelo tipado y Livewire recibe
     * el id como texto (TypeError "Cannot assign string"). El id llega a esta
     * acción y Blade marca la opción seleccionada con `@selected`.
     */
    public function seleccionarGrupo(string $grupoId): void
    {
        $grupo = $grupoId !== '' ? Grupo::find($grupoId) : null;

        // Portal docente (2.0-15): no se puede apuntar a un grupo ajeno.
        if ($grupo !== null) {
            $this->autorizarGrupoSiDocente($grupo);
        }

        $this->grupo = $grupo;
        $this->resetErrorBag();
    }

    public function abrirModalCapturar(InscripcionMateria $inscripcion, Evaluacion $evaluacion): void
    {
        Gate::authorize('calificacion.crear');
        $this->resetErrorBag();

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

        $this->inscripcionCaptura = $inscripcion;
        $this->evaluacionCaptura = $evaluacion;
        $this->modalCalificacionAbierto = true;
    }

    public function cerrarModalCalificacion(): void
    {
        $this->modalCalificacionAbierto = false;
        $this->calificacionEnEdicion = null;
        $this->inscripcionCaptura = null;
        $this->evaluacionCaptura = null;
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

        if (! $this->inscripcionCaptura || ! $this->evaluacionCaptura) {
            $this->addError('valor', 'Selecciona una calificación de la matriz para capturarla.');
            return;
        }

        try {
            $service = app(\App\Services\CalificacionService::class);
            $service->capturar(
                $this->inscripcionCaptura,
                $this->evaluacionCaptura,
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

        $grupos = $this->limitarGruposAlDocente(
            Grupo::with(['materia', 'periodo', 'docente'])
                ->where('activo', true)
        )->orderBy('clave')->get();

        return view('livewire.academico.calificacion-admin', [
            'grupo' => $this->grupo,
            'grupos' => $grupos,
            'evaluaciones' => $evaluaciones,
            'inscripciones' => $inscripciones,
        ]);
    }
}