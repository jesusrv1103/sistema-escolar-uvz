<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusEvaluacion;
use App\Enums\TipoEvaluacion;
use App\Livewire\Academico\CrearEvaluacion;
use App\Livewire\Academico\EditarEvaluacion;
use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class EvaluacionAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroGrupo = '';
    public string $filtroEstatus = '';
    public string $filtroTipo = '';

    public ?Grupo $grupo = null;

    protected $listeners = ['evaluacionCreada', 'evaluacionActualizada', 'evaluacionEliminada'];

    public function mount(?Grupo $grupo = null): void
    {
        Gate::authorize('evaluacion.crear');
        $this->grupo = $grupo;
    }

    public function evaluacionCreada(): void
    {
        $this->resetPage();
    }

    public function evaluacionActualizada(): void
    {
        $this->resetPage();
    }

    public function evaluacionEliminada(): void
    {
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->dispatch('abrir-modal-crear', grupoId: $this->grupo?->id);
    }

    public function abrirModalEditar(Evaluacion $evaluacion): void
    {
        Gate::authorize('evaluacion.actualizar', $evaluacion);
        $this->dispatch('abrir-modal-editar', evaluacionId: $evaluacion->id);
    }

    public function publicar(Evaluacion $evaluacion): void
    {
        Gate::authorize('evaluacion.publicar', $evaluacion);

        try {
            $service = app(\App\Services\EvaluacionService::class);
            $service->publicar($evaluacion);
            session()->flash('success', 'Evaluación publicada correctamente.');
        } catch (\App\Exceptions\EvaluacionException $e) {
            $this->addError('publicar', $e->getMessage());
        }
    }

    public function cerrar(Evaluacion $evaluacion): void
    {
        Gate::authorize('evaluacion.cerrar', $evaluacion->grupo);

        try {
            $service = app(\App\Services\EvaluacionService::class);
            $service->cerrar($evaluacion);
            session()->flash('success', 'Evaluación cerrada correctamente.');
        } catch (\App\Exceptions\EvaluacionException $e) {
            $this->addError('cerrar', $e->getMessage());
        }
    }

    public function eliminar(Evaluacion $evaluacion): void
    {
        Gate::authorize('evaluacion.eliminar', $evaluacion);

        try {
            $service = app(\App\Services\EvaluacionService::class);
            $service->eliminar($evaluacion);
            session()->flash('success', 'Evaluación eliminada correctamente.');
        } catch (\App\Exceptions\EvaluacionException $e) {
            $this->addError('eliminar', $e->getMessage());
        }
    }

    public function render()
    {
        $evaluaciones = $this->grupo
            ? $this->grupo->evaluaciones()
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
                ->when($this->filtroEstatus !== '', fn ($q) => $q->where('estatus', $this->filtroEstatus))
                ->when($this->filtroTipo !== '', fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->with(['grupo', 'creadaPor'])
                ->orderBy('fecha', 'desc')
                ->paginate(15)
            : \App\Models\Evaluacion::query()
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
                ->when($this->filtroGrupo !== '', fn ($q) => $q->where('grupo_id', $this->filtroGrupo))
                ->when($this->filtroEstatus !== '', fn ($q) => $q->where('estatus', $this->filtroEstatus))
                ->when($this->filtroTipo !== '', fn ($q) => $q->where('tipo', $this->filtroTipo))
                ->with(['grupo', 'grupo.materia', 'creadaPor'])
                ->orderBy('fecha', 'desc')
                ->paginate(15);

        $grupos = \App\Models\Grupo::with(['materia', 'periodo'])->orderBy('clave')->get();
        $tipos = \App\Enums\TipoEvaluacion::cases();
        $estatuses = \App\Models\Evaluacion::select('estatus')->distinct()->pluck('estatus');

        return view('livewire.academico.evaluacion-admin', [
            'grupo' => $this->grupo,
            'evaluaciones' => $evaluaciones,
            'grupos' => $grupos,
            'tipos' => \App\Enums\TipoEvaluacion::cases(),
            'estatuses' => \App\Enums\EstatusEvaluacion::cases(),
        ]);
    }
}