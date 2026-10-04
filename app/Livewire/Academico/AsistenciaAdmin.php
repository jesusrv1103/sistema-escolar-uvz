<?php

namespace App\Livewire\Academico;

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
    use WithPagination;

    public string $busqueda = '';
    public ?Grupo $grupo = null;
    public string $fecha = '';
    public array $asistencias = [];

    protected $listeners = ['asistenciaRegistrada', 'asistenciaActualizada'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('asistencia.crear');
        $this->grupo = $grupo;
        $this->fecha = now()->format('Y-m-d');
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
            ->orderBy('matriculaPeriodo.alumno.apellidos')
            ->orderBy('matriculaPeriodo.alumno.nombre')
            ->get();

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
        $grupos = Grupo::with(['materia', 'periodo', 'docente'])
            ->where('activo', true)
            ->orderBy('clave')
            ->get();

        return view('livewire.academico.asistencia-admin', [
            'grupos' => $grupos,
        ]);
    }
}