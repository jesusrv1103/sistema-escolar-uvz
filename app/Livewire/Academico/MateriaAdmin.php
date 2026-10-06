<?php

namespace App\Livewire\Academico;

use App\Models\Materia;
use App\Models\PlanVersion;
use App\Models\PlanEstudio;
use App\Services\MateriaPrerrequisitoService;
use App\Enums\TipoPrerrequisito;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Materias (solo ADMIN).
 * Solo editable si la versión está en PROPUESTA.
 */
#[Layout('layouts.app')]
class MateriaAdmin extends Component
{
    use \Livewire\WithPagination;

    public ?PlanVersion $version = null;
    public ?PlanEstudio $plan = null;
    public string $busqueda = '';
    public string $filtroPeriodo = '';
    public string $filtroActivo = '';

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?Materia $materiaEnEdicion = null;
    public string $clave = '';
    public string $nombre = '';
    public int $creditos = 3;
    public ?int $horasTeoricas = null;
    public ?int $horasPracticas = null;
    public int $periodoOrden = 1;
    public string $bloque = '';
    public bool $obligatoria = true;
    public bool $activo = true;

    // Modal prerrequisitos
    public bool $modalPrerreqAbierto = false;
    public ?Materia $materiaPrerreq = null;
    public string $tipoPrerreq = 'OBLIGATORIO';
    public string $busquedaPrerreq = '';

    public function mount(?PlanVersion $version = null): void
    {
        Gate::authorize('materia.create');
        $this->version = $version;
        $this->plan = $version?->plan;
    }

    // --- Modal Crear/Editar Materia ---

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(Materia $materia): void
    {
        Gate::authorize('materia.update', $materia);

        if (!$materia->planVersion->esPropuesta()) {
            $this->addError('materia', 'Solo se pueden editar materias de versiones en estado Propuesta.');
            return;
        }

        $this->materiaEnEdicion = $materia;
        $this->clave = $materia->clave;
        $this->nombre = $materia->nombre;
        $this->creditos = $materia->creditos;
        $this->horasTeoricas = $materia->horas_teoricas;
        $this->horasPracticas = $materia->horas_practicas;
        $this->periodoOrden = $materia->periodo_orden;
        $this->bloque = $materia->bloque ?? '';
        $this->obligatoria = $materia->obligatoria;
        $this->activo = $materia->activo;
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->materiaEnEdicion = null;
        $this->clave = '';
        $this->nombre = '';
        $this->creditos = 3;
        $this->horasTeoricas = null;
        $this->horasPracticas = null;
        $this->periodoOrden = 1;
        $this->bloque = '';
        $this->obligatoria = true;
        $this->activo = true;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/'],
            'nombre' => ['required', 'string', 'max:150'],
            'creditos' => ['required', 'integer', 'min:1'],
            'horasTeoricas' => ['nullable', 'integer', 'min:0'],
            'horasPracticas' => ['nullable', 'integer', 'min:0'],
            'periodoOrden' => ['required', 'integer', 'min:1'],
            'bloque' => ['nullable', 'string', 'max:30'],
            'obligatoria' => ['boolean'],
            'activo' => ['boolean'],
        ]);

        if ($this->materiaEnEdicion === null) {
            Gate::authorize('materia.create');
            $this->crear();
        } else {
            Gate::authorize('materia.update', $this->materiaEnEdicion);

            // Solo editar si versión en PROPUESTA
            if (!$this->materiaEnEdicion->planVersion->esPropuesta()) {
                $this->addError('materia', 'Solo se pueden editar materias de versiones en estado Propuesta.');
                return;
            }

            $this->actualizar();
        }
    }

    private function crear(): void
    {
        // Verificar unicidad de clave en la versión
        $existe = Materia::where('plan_version_id', $this->version->id)
            ->where('clave', $this->clave)
            ->exists();

        if ($existe) {
            $this->addError('clave', 'Ya existe una materia con esta clave en la versión.');
            return;
        }

        $materia = $this->version->materias()->create([
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'creditos' => $this->creditos,
            'horas_teoricas' => $this->horasTeoricas,
            'horas_practicas' => $this->horasPracticas,
            'periodo_orden' => $this->periodoOrden,
            'bloque' => $this->bloque ?: null,
            'obligatoria' => $this->obligatoria,
            'activo' => $this->activo,
        ]);

        session()->flash('success', "Materia '{$materia->nombre}' creada correctamente.");
        $this->cerrarModal();
    }

    private function actualizar(): void
    {
        // Verificar unicidad de clave en la versión (excluyendo la actual)
        $existe = Materia::where('plan_version_id', $this->materiaEnEdicion->plan_version_id)
            ->where('clave', $this->clave)
            ->where('id', '!=', $this->materiaEnEdicion->id)
            ->exists();

        if ($existe) {
            $this->addError('clave', 'Ya existe una materia con esta clave en la versión.');
            return;
        }

        $this->materiaEnEdicion->update([
            'clave' => $this->clave,
            'nombre' => $this->nombre,
            'creditos' => $this->creditos,
            'horas_teoricas' => $this->horasTeoricas,
            'horas_practicas' => $this->horasPracticas,
            'periodo_orden' => $this->periodoOrden,
            'bloque' => $this->bloque ?: null,
            'obligatoria' => $this->obligatoria,
            'activo' => $this->activo,
        ]);

        session()->flash('success', "Materia '{$this->materiaEnEdicion->nombre}' actualizada correctamente.");
        $this->cerrarModal();
    }

    public function alternarActivo(Materia $materia): void
    {
        Gate::authorize('materia.update', $materia);

        if (!$materia->planVersion->esPropuesta()) {
            $this->addError('materia', 'Solo se pueden activar/desactivar materias de versiones en estado Propuesta.');
            return;
        }

        $materia->update(['activo' => ! $materia->activo]);

        session()->flash('success', "Materia " . ($materia->activo ? 'activada' : 'desactivada') . " correctamente.");
    }

    public function eliminar(Materia $materia): void
    {
        Gate::authorize('materia.delete', $materia);

        if (!$materia->planVersion->esPropuesta()) {
            $this->addError('materia', 'Solo se pueden eliminar materias de versiones en estado Propuesta.');
            return;
        }

        $nombre = $materia->nombre;
        $materia->delete();

        session()->flash('success', "Materia '{$nombre}' eliminada correctamente.");
    }

    // --- Prerrequisitos ---

    public function abrirModalPrerreq(Materia $materia): void
    {
        Gate::authorize('prerrequisito.gestionar');
        $this->materiaPrerreq = $materia;
        $this->modalPrerreqAbierto = true;
        $this->busquedaPrerreq = '';
    }

    public function cerrarModalPrerreq(): void
    {
        $this->modalPrerreqAbierto = false;
        $this->materiaPrerreq = null;
        $this->busquedaPrerreq = '';
    }

    public function agregarPrerrequisito(Materia $prerrequisito): void
    {
        if ($this->materiaPrerreq === null) {
            return;
        }

        $tipoEnum = \App\Enums\TipoPrerrequisito::tryFrom($this->tipoPrerreq);
        if ($tipoEnum === null) {
            $this->addError('prerreq', "Tipo de prerrequisito inválido: {$this->tipoPrerreq}. Valores permitidos: OBLIGATORIO, RECOMENDADO.");
            return;
        }

        try {
            app(\App\Services\MateriaPrerrequisitoService::class)->agregar(
                $this->materiaPrerreq,
                $prerrequisito,
                $tipoEnum
            );

            session()->flash('success', "Prerrequisito '{$prerrequisito->nombre}' agregado como {$tipoEnum->label()}.");
        } catch (\App\Exceptions\PrerrequisitoInvalidoException $e) {
            $this->addError('prerreq', $e->getMessage());
        } catch (\App\Exceptions\CicloPrerrequisitoException $e) {
            $this->addError('prerreq', $e->getMessage());
        }
    }

    public function quitarPrerrequisito(Materia $prerrequisito): void
    {
        if ($this->materiaPrerreq === null) {
            return;
        }

        app(\App\Services\MateriaPrerrequisitoService::class)->quitar($this->materiaPrerreq, $prerrequisito);

        session()->flash('success', "Prerrequisito '{$prerrequisito->nombre}' eliminado.");
    }

    public function render()
    {
        $materias = $this->version
            ? $this->version->materias()
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('clave', 'like', "%{$this->busqueda}%"))
                ->when($this->filtroPeriodo !== '', fn ($q) => $q->where('periodo_orden', $this->filtroPeriodo))
                ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
                ->orderBy('periodo_orden')
                ->orderBy('clave')
                ->paginate(15)
            : Materia::query()
                ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('clave', 'like', "%{$this->busqueda}%"))
                ->orderBy('plan_version_id')
                ->orderBy('periodo_orden')
                ->orderBy('clave')
                ->paginate(15);

        $periodos = $this->version
            ? $this->version->materias()->select('periodo_orden')->distinct()->orderBy('periodo_orden')->pluck('periodo_orden')
            : [];

        return view('livewire.academico.materia', [
            'version' => $this->version,
            'plan' => $this->plan,
            'materias' => $materias,
            'periodos' => $periodos,
        ]);
    }
}