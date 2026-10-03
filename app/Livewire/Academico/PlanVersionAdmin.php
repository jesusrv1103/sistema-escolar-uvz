<?php

namespace App\Livewire\Academico;

use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Services\PlanVersionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Versiones de Plan (solo ADMIN).
 * Aprobación vía PlanVersionService (lockForUpdate + transacción atómica).
 */
#[Layout('layouts.app')]
class PlanVersionAdmin extends Component
{
    use \Livewire\WithPagination;

    public ?PlanEstudio $plan = null;
    public string $busqueda = '';

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?PlanVersion $versionEnEdicion = null;
    public string $version = '';
    public ?string $fechaVigencia = null;

    // Aprobar
    public bool $modalAprobarAbierto = false;
    public ?PlanVersion $versionAAprobar = null;

    public function mount(?PlanEstudio $plan = null): void
    {
        Gate::authorize('plan_version.create');
        $this->plan = $plan;
    }

    // --- Modal Crear/Editar ---

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(PlanVersion $version): void
    {
        Gate::authorize('plan_version.update', $version);

        $this->versionEnEdicion = $version;
        $this->version = $version->version;
        $this->fechaVigencia = $version->fecha_vigencia?->format('Y-m-d') ?? '';
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->versionEnEdicion = null;
        $this->version = '';
        $this->fechaVigencia = '';
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        if ($this->versionEnEdicion === null) {
            $this->validate([
                'version' => ['required', 'string', 'max:20', 'unique:plan_versiones,version,NULL,id,plan_id,' . $this->plan->id],
                'fechaVigencia' => ['nullable', 'date'],
            ]);
        } else {
            $this->validate([
                'version' => ['required', 'string', 'max:20'],
                'fechaVigencia' => ['nullable', 'date'],
            ]);
        }

        if ($this->versionEnEdicion === null) {
            Gate::authorize('plan_version.create');
            $this->crear();
        } else {
            Gate::authorize('plan_version.update', $this->versionEnEdicion);
            $this->actualizar();
        }
    }

    private function crear(): void
    {
        $this->plan->versiones()->create([
            'version' => $this->version,
            'fecha_vigencia' => $this->fechaVigencia ?: null,
            'estatus' => 'PROPUESTA',
        ]);

        session()->flash('success', "Versión '{$this->version}' creada en estado Propuesta.");
        $this->cerrarModal();
    }

    private function actualizar(): void
    {
        // Solo se permite editar si está en PROPUESTA
        if (!$this->versionEnEdicion->esPropuesta()) {
            $this->addError('version', 'Solo se pueden editar versiones en estado Propuesta.');
            return;
        }

        $this->versionEnEdicion->update([
            'version' => $this->version,
            'fecha_vigencia' => $this->fechaVigencia ?: null,
        ]);

        session()->flash('success', "Versión '{$this->version}' actualizada correctamente.");
        $this->cerrarModal();
    }

    // --- Aprobar (PROPUESTA -> VIGENTE) ---

    public function abrirModalAprobar(PlanVersion $version): void
    {
        Gate::authorize('plan_version.aprobar', $version);

        if (!$version->esPropuesta()) {
            $this->addError('version', 'Solo se pueden aprobar versiones en estado Propuesta.');
            return;
        }

        if ($version->fecha_vigencia === null) {
            $this->addError('version', 'La versión debe tener fecha de vigencia para ser aprobada.');
            return;
        }

        $this->versionAAprobar = $version;
        $this->modalAprobarAbierto = true;
    }

    public function cerrarModalAprobar(): void
    {
        $this->modalAprobarAbierto = false;
        $this->versionAAprobar = null;
    }

    public function confirmarAprobar(): void
    {
        if ($this->versionAAprobar === null) {
            return;
        }

        try {
            app(PlanVersionService::class)->aprobar($this->versionAAprobar);
            session()->flash('success', "Versión '{$this->versionAAprobar->version}' aprobada. Ahora está Vigente.");
        } catch (\App\Exceptions\PlanVersionInvalidaException $e) {
            $this->addError('version', $e->getMessage());
            return;
        }

        $this->cerrarModalAprobar();
    }

    public function eliminar(PlanVersion $version): void
    {
        Gate::authorize('plan_version.delete', $version);

        // Solo permitir eliminar si está en PROPUESTA
        if (!$version->esPropuesta()) {
            $this->addError('version', 'Solo se pueden eliminar versiones en estado Propuesta.');
            return;
        }

        $ver = $version->version;
        $version->delete();

        session()->flash('success', "Versión '{$ver}' eliminada correctamente.");
    }

    public function render()
    {
        $versiones = $this->plan
            ? $this->plan->versiones()->with('aprobadoPor')->latest()->paginate(15)
            : PlanVersion::with('plan', 'aprobadoPor')->latest()->paginate(15);

        return view('livewire.academico.plan-version', [
            'plan' => $this->plan,
            'versiones' => $versiones,
        ]);
    }
}