<?php

namespace App\Livewire\Academico;

use App\Models\Materia;
use App\Models\PlanEstudio;
use App\Models\PlanVersion;
use App\Models\Programa;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado global de Materias — PANTALLA DE ENTRADA (navegabilidad 2.0-12).
 *
 * El módulo vivía sólo dentro de Plan → Versión → Materias, encadenado en URLs
 * que el usuario no podía descubrir. Esta pantalla ofrece la entrada
 * Programa → Plan → Versión → Materias y enlaza a la gestión real.
 *
 * Es SOLO de consulta/navegación: no crea, edita ni borra materias. El CRUD
 * sigue siendo MateriaAdmin con MateriaService/políticas sin tocar, y la
 * autorización es la misma puerta que ya exige esa pantalla (`materia.create`,
 * capacidad admin-only): aquí no se decide nada nuevo ni se otorgan permisos.
 */
#[Layout('layouts.app')]
class MateriasIndex extends Component
{
    use WithPagination;

    /** Texto de búsqueda sobre clave o nombre de la materia. */
    public string $busqueda = '';

    /** Filtros en cascada: programa → plan → versión. */
    public string $filtroPrograma = '';
    public string $filtroPlan = '';
    public string $filtroVersion = '';

    public function mount(): void
    {
        // Misma autorización que exige MateriaAdmin (quien administra materias).
        Gate::authorize('materia.create');
    }

    /**
     * Al cambiar un filtro se reinicia la paginación y se descartan los filtros
     * hijos que ya no aplican (programa → plan → versión).
     */
    public function updated(string $property): void
    {
        if (! in_array($property, ['busqueda', 'filtroPrograma', 'filtroPlan', 'filtroVersion'], true)) {
            return;
        }

        $this->resetPage();

        if ($property === 'filtroPrograma'
            && $this->filtroPlan !== ''
            && ! PlanEstudio::whereKey($this->filtroPlan)->where('programa_id', $this->filtroPrograma)->exists()) {
            $this->filtroPlan = '';
            $this->filtroVersion = '';
        }

        if ($property === 'filtroPlan'
            && $this->filtroVersion !== ''
            && $this->filtroPlan !== ''
            && ! PlanVersion::whereKey($this->filtroVersion)->where('plan_id', $this->filtroPlan)->exists()) {
            $this->filtroVersion = '';
        }
    }

    /** UX (2.0-12): limpia los filtros del listado y vuelve a la primera página. */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroPrograma = '';
        $this->filtroPlan = '';
        $this->filtroVersion = '';
        $this->resetPage();
    }

    public function render()
    {
        $materias = Materia::query()
            ->with(['planVersion.plan.programa'])
            ->when($this->busqueda !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('clave', 'like', "%{$this->busqueda}%")
            ))
            ->when($this->filtroVersion !== '', fn ($q) => $q->where('plan_version_id', $this->filtroVersion))
            ->when($this->filtroPlan !== '', fn ($q) => $q->whereHas(
                'planVersion',
                fn ($w) => $w->where('plan_id', $this->filtroPlan)
            ))
            ->when($this->filtroPrograma !== '', fn ($q) => $q->whereHas(
                'planVersion.plan',
                fn ($w) => $w->where('programa_id', $this->filtroPrograma)
            ))
            ->orderBy('plan_version_id')
            ->orderBy('periodo_orden')
            ->orderBy('clave')
            ->paginate(15);

        return view('livewire.academico.materias-index', [
            'materias' => $materias,
            'programas' => Programa::where('activo', true)->orderBy('nombre')->get(),
            'planes' => PlanEstudio::query()
                ->when($this->filtroPrograma !== '', fn ($q) => $q->where('programa_id', $this->filtroPrograma))
                ->orderBy('nombre')
                ->get(),
            'versiones' => PlanVersion::query()
                ->when($this->filtroPlan !== '', fn ($q) => $q->where('plan_id', $this->filtroPlan))
                ->with('plan')
                ->orderBy('version')
                ->get(),
        ]);
    }
}
