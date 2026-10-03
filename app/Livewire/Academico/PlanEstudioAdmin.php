<?php

namespace App\Livewire\Academico;

use App\Models\PlanEstudio;
use App\Models\Programa;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Planes de Estudio (solo ADMIN).
 */
#[Layout('layouts.app')]
class PlanEstudioAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroPrograma = '';
    public string $filtroActivo = '';

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?PlanEstudio $planEnEdicion = null;
    public string $clave = '';
    public string $nombre = '';
    public string $resolucionRvoe = '';
    public ?string $fechaResolucion = null;
    public bool $activo = true;
    public ?int $programaId = null;

    protected $listeners = ['planCreado', 'planActualizado'];

    public function mount(): void
    {
        Gate::authorize('plan_estudio.create');
    }

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(PlanEstudio $plan): void
    {
        $this->planEnEdicion = $plan;
        $this->clave = $plan->clave;
        $this->nombre = $plan->nombre;
        $this->resolucionRvoe = $plan->resolucion_rvoe ?? '';
        $this->fechaResolucion = $plan->fecha_resolucion?->format('Y-m-d') ?? '';
        $this->activo = $plan->activo;
        $this->programaId = $plan->programa_id;
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->planEnEdicion = null;
        $this->clave = '';
        $this->nombre = '';
        $this->resolucionRvoe = '';
        $this->fechaResolucion = '';
        $this->activo = true;
        $this->programaId = null;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'],
            'nombre' => ['required', 'string', 'max:150'],
            'resolucionRvoe' => ['nullable', 'string', 'max:100'],
            'fechaResolucion' => ['nullable', 'date'],
            'activo' => ['boolean'],
            'programaId' => ['required', 'integer', 'exists:programas,id'],
        ]);

        if ($this->planEnEdicion === null) {
            Gate::authorize('plan_estudio.create');
            $this->crear();
        } else {
            Gate::authorize('plan_estudio.update', $this->planEnEdicion);
            $this->actualizar();
        }
    }

    private function crear(): void
    {
        $plan = PlanEstudio::create([
            'programa_id' => $this->programaId,
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'resolucion_rvoe' => $this->resolucionRvoe ?: null,
            'fecha_resolucion' => $this->fechaResolucion ?: null,
            'activo' => $this->activo,
        ]);

        session()->flash('success', "Plan de estudio '{$plan->nombre}' creado correctamente.");
        $this->cerrarModal();
        $this->dispatch('planCreado');
    }

    private function actualizar(): void
    {
        $this->planEnEdicion->update([
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'resolucion_rvoe' => $this->resolucionRvoe ?: null,
            'fecha_resolucion' => $this->fechaResolucion ?: null,
            'activo' => $this->activo,
            'programa_id' => $this->programaId,
        ]);

        session()->flash('success', "Plan de estudio '{$this->planEnEdicion->nombre}' actualizado correctamente.");
        $this->cerrarModal();
        $this->dispatch('planActualizado');
    }

    public function alternarActivo(PlanEstudio $plan): void
    {
        Gate::authorize('plan_estudio.update', $plan);

        $plan->update(['activo' => ! $plan->activo]);

        session()->flash('success', "Plan de estudio " . ($plan->activo ? 'activado' : 'desactivado') . " correctamente.");
    }

    public function eliminar(PlanEstudio $plan): void
    {
        Gate::authorize('plan_estudio.delete', $plan);

        $nombre = $plan->nombre;
        $plan->delete();

        session()->flash('success', "Plan de estudio '{$nombre}' eliminado correctamente.");
    }

    public function render()
    {
        $query = PlanEstudio::with('programa')
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))
            ->when($this->filtroPrograma, fn ($q) => $q->where('programa_id', $this->filtroPrograma))
            ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
            ->orderBy('nombre');

        $planes = $query->paginate(15);
        $programas = Programa::where('activo', true)->orderBy('nombre')->get();

        return view('livewire.academico.plan-estudio', [
            'planes' => $planes,
            'programas' => $programas,
        ]);
    }
}