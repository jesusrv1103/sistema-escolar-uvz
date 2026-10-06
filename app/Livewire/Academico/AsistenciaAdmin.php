<?php

namespace App\Livewire\Academico;

use App\Livewire\Concerns\AutorizaGrupoDocente;
use App\Models\Asistencia;
use App\Models\Grupo;
use App\Models\InscripcionMateria;
use App\Services\AsistenciaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AsistenciaAdmin extends Component
{
    use AutorizaGrupoDocente, WithPagination;

    public string $busqueda = '';
    public ?Grupo $grupo = null;
    public string $fecha = '';
    public array $asistencias = [];

    protected $listeners = ['asistenciaRegistrada', 'asistenciaActualizada'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('asistencia.crear');
        // Portal docente (2.0-15): el DOCENTE sólo entra por un grupo propio.
        $this->autorizarGrupoSiDocente($grupo);
        // Un Grupo vacío (resolución por contenedor sin id de ruta) no es una
        // selección: se normaliza a null para no listar con `grupo_id null`.
        $this->grupo = $grupo?->exists ? $grupo : null;
        $this->fecha = now()->format('Y-m-d');
    }

    /**
     * Selección de grupo desde el filtro.
     *
     * No puede ser `wire:model`: `grupo` es un modelo tipado y Livewire recibe
     * el id como texto (TypeError "Cannot assign string"). El id llega a esta
     * acción y Blade marca la opción con `@selected`.
     */
    public function seleccionarGrupo(string $grupoId): void
    {
        $grupo = $grupoId !== '' ? Grupo::find($grupoId) : null;

        // Portal docente (2.0-15): no se puede apuntar a un grupo ajeno.
        if ($grupo !== null) {
            $this->autorizarGrupoSiDocente($grupo);
        }

        $this->grupo = $grupo;
        // La lista cargada pertenecía al grupo anterior.
        $this->asistencias = [];
        $this->resetErrorBag();
        $this->resetPage();
    }

    public function asistenciaRegistrada(): void
    {
        $this->resetPage();
        session()->flash('success', 'Asistencia registrada correctamente.');
    }

    public function asistenciaActualizada(): void
    {
        $this->resetPage();
        session()->flash('success', 'Asistencia actualizada correctamente.');
    }

    public function cargarLista(): void
    {
        if (!$this->grupo) {
            $this->addError('grupo', 'Seleccione un grupo');
            return;
        }

        $this->asistencias = [];

        $inscripciones = InscripcionMateria::where('grupo_id', $this->grupo->id)
            ->where('estatus', \App\Enums\EstatusInscripcionMateria::CURSANDO)
            ->with('matriculaPeriodo.alumno')
            ->get()
            // `matriculaPeriodo.alumno.apellidos` no es un orden SQL válido: no existe
            // tal columna y una relación no puede ordenar la consulta. El orden se hace
            // en PHP sobre la relación ya cargada (SQLSTATE 42S22 antes de este fix).
            ->sortBy(fn ($inscripcion) => mb_strtolower(trim(
                ($inscripcion->matriculaPeriodo?->alumno?->apellidos ?? '') . ' ' .
                ($inscripcion->matriculaPeriodo?->alumno?->nombre ?? '')
            )))
            ->values();

        foreach ($inscripciones as $inscripcion) {
            $asistencia = Asistencia::where('inscripcion_materia_id', $inscripcion->id)
                ->where('fecha', $this->fecha)
                ->first();

            $this->asistencias[$inscripcion->id] = [
                'inscripcion' => $inscripcion,
                'presente' => $asistencia->presente ?? false,
                'id' => $asistencia->id ?? null,
            ];
        }
    }

    public function guardar(): void
    {
        if (!$this->grupo) {
            $this->addError('grupo', 'Seleccione un grupo');
            return;
        }

        if (!$this->fecha) {
            $this->addError('fecha', 'Seleccione una fecha');
            return;
        }

        try {
            $service = app(AsistenciaService::class);
            $service->registrarMasivo(
                grupo: $this->grupo,
                fecha: $this->fecha,
                asistencias: $this->asistencias,
                usuario: auth()->user(),
            );

            $this->dispatch('asistenciaRegistrada');
        } catch (\App\Exceptions\AsistenciaException $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function render()
    {
        $grupos = $this->limitarGruposAlDocente(
            Grupo::with(['materia', 'periodo', 'docente'])
                ->where('activo', true)
        )->orderBy('clave')->get();

        return view('livewire.academico.asistencia-admin', [
            'grupos' => $grupos,
        ]);
    }
}