<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusGrupo;
use App\Enums\TurnoGrupo;
use App\Models\Aula;
use App\Models\Docente;
use App\Models\Grupo;
use App\Models\Materia;
use App\Models\Periodo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CRUD de Grupos (solo ADMIN).
 */
#[Layout('layouts.app')]
class GrupoAdmin extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroPeriodo = '';
    public string $filtroMateria = '';
    public string $filtroDocente = '';
    public string $filtroEstatus = '';
    public string $filtroActivo = '';

    // Modal crear/editar
    public bool $modalAbierto = false;
    public ?Grupo $grupoEnEdicion = null;
    public string $clave = '';
    public string $nombre = '';
    public int $cupo = 30;
    public string $turno = 'MATUTINO';
    public int $materiaId = 0;
    public int $periodoId = 0;
    public ?int $docenteId = null;
    public ?int $aulaId = null;
    public string $estatus = 'CREADO';

    public function mount(): void
    {
        Gate::authorize('grupo.create');
    }

    // --- Modal Crear/Editar ---

    public function abrirModalCrear(): void
    {
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(Grupo $grupo): void
    {
        Gate::authorize('grupo.update', $grupo);

        $this->grupoEnEdicion = $grupo;
        $this->clave = $grupo->clave;
        $this->nombre = $grupo->nombre ?? '';
        $this->cupo = $grupo->cupo;
        $this->turno = $grupo->turno;
        $this->materiaId = $grupo->materia_id;
        $this->periodoId = $grupo->periodo_id;
        $this->docenteId = $grupo->docente_id;
        $this->aulaId = $grupo->aula_id;
        $this->estatus = $grupo->estatus->value;
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->grupoEnEdicion = null;
        $this->clave = '';
        $this->nombre = '';
        $this->cupo = 30;
        $this->turno = 'MATUTINO';
        $this->materiaId = 0;
        $this->periodoId = 0;
        $this->docenteId = null;
        $this->aulaId = null;
        $this->estatus = 'CREADO';
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'clave' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', 'unique:grupos,clave,' . ($this->grupoEnEdicion?->id ?? 'NULL') . ',id,materia_id,' . $this->materiaId . ',periodo_id,' . $this->periodoId],
            'nombre' => ['nullable', 'string', 'max:150'],
            'cupo' => ['required', 'integer', 'min:1', 'max:500'],
            'turno' => ['required', 'string', 'in:MATUTINO,VESPERTINO,NOCTURNO'],
            'materiaId' => ['required', 'integer', 'exists:materias,id'],
            'periodoId' => ['required', 'integer', 'exists:periodos,id'],
            'docenteId' => ['nullable', 'integer', 'exists:docentes,id'],
            'aulaId' => ['nullable', 'integer', 'exists:aulas,id'],
            'estatus' => ['required', 'string', 'in:CREADO,ABIERTO,EN_CURSO,CERRADO,CANCELADO'],
        ]);

        if ($this->grupoEnEdicion === null) {
            Gate::authorize('grupo.create');
            $this->crear();
        } else {
            Gate::authorize('grupo.update', $this->grupoEnEdicion);
            $this->actualizar();
        }
    }

    private function crear(): void
    {
        $materia = Materia::findOrFail($this->materiaId);
        $periodo = Periodo::findOrFail($this->periodoId);

        $nombre = $this->nombre ?: "{$materia->nombre} - Grupo {$this->clave}";

        $grupo = Grupo::create([
            'materia_id' => $this->materiaId,
            'periodo_id' => $this->periodoId,
            'docente_id' => $this->docenteId,
            'aula_id' => $this->aulaId,
            'clave' => $this->clave,
            'nombre' => $nombre,
            'cupo' => $this->cupo,
            'turno' => $this->turno,
            'estatus' => $this->estatus,
        ]);

        session()->flash('success', "Grupo '{$grupo->clave}' creado correctamente.");
        $this->cerrarModal();
        $this->dispatch('grupoCreado');
    }

    private function actualizar(): void
    {
        // Solo se permite editar si está en CREADO
        if (!$this->grupoEnEdicion->esCreado()) {
            $this->addError('estatus', 'Solo se pueden editar grupos en estado Creado.');
            return;
        }

        $materia = $this->grupoEnEdicion->materia;
        $nombre = $this->nombre ?: "{$materia->nombre} - Grupo {$this->clave}";

        $this->grupoEnEdicion->update([
            'clave' => $this->clave,
            'nombre' => $nombre,
            'cupo' => $this->cupo,
            'turno' => $this->turno,
            'docente_id' => $this->docenteId,
            'aula_id' => $this->aulaId,
            'estatus' => $this->estatus,
        ]);

        session()->flash('success', "Grupo '{$this->grupoEnEdicion->clave}' actualizado correctamente.");
        $this->cerrarModal();
        $this->dispatch('grupoActualizado');
    }

    public function alternarActivo(Grupo $grupo): void
    {
        Gate::authorize('grupo.update', $grupo);

        if (!$grupo->esCreado()) {
            $this->addError('grupo', 'Solo se pueden activar/desactivar grupos en estado Creado.');
            return;
        }

        $grupo->update(['activo' => ! $grupo->activo]);

        session()->flash('success', "Grupo " . ($grupo->activo ? 'activado' : 'desactivado') . " correctamente.");
    }

    public function abrirModalHorarios(Grupo $grupo): void
    {
        // TODO: Implementar modal de horarios
    }

    /** UX (2.0-12): limpia los filtros del listado y vuelve a la primera página. */
    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroPeriodo = '';
        $this->filtroMateria = '';
        $this->filtroDocente = '';
        $this->filtroEstatus = '';
        $this->filtroActivo = '';
        $this->resetPage();
    }

    public function render()
    {
        $query = Grupo::with(['materia', 'periodo', 'docente', 'aula'])
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('clave', 'like', "%{$this->busqueda}%"))
            ->when($this->filtroPeriodo, fn ($q) => $q->where('periodo_id', $this->filtroPeriodo))
            ->when($this->filtroMateria, fn ($q) => $q->where('materia_id', $this->filtroMateria))
            ->when($this->filtroDocente, fn ($q) => $q->where('docente_id', $this->filtroDocente))
            ->when($this->filtroEstatus, fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->when($this->filtroActivo !== '', fn ($q) => $q->where('activo', $this->filtroActivo === '1'))
            ->orderBy('periodo_id')
            ->orderBy('materia_id')
            ->orderBy('clave');

        $grupos = $query->paginate(15);
        $periodos = Periodo::where('activo', true)->orderBy('fecha_inicio', 'desc')->get();
        $materias = Materia::where('activo', true)->orderBy('nombre')->get();
        $docentes = Docente::where('activo', true)->orderBy('apellidos')->orderBy('nombre')->get();
        $aulas = Aula::where('activo', true)->orderBy('edificio')->orderBy('nombre')->get();

        return view('livewire.academico.grupo', [
            'grupos' => $grupos,
            'periodos' => $periodos,
            'materias' => $materias,
            'docentes' => $docentes,
            'aulas' => $aulas,
        ]);
    }
}