<?php

namespace App\Livewire\Academico;

use App\Livewire\Concerns\AutorizaGrupoDocente;
use App\Models\Acta;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Services\ActaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ActaAdmin extends Component
{
    use AutorizaGrupoDocente, WithPagination;

    public string $busqueda = '';
    public string $filtroPeriodo = '';
    public string $filtroEstatus = '';

    /** Grupo de entrada en el portal docente (null = listado completo admin). */
    public ?Grupo $grupo = null;

    protected $listeners = ['actaCreada', 'actaCerrada'];

    public function mount(?Grupo $grupo = null): void
    {
        // Portal docente (2.0-15): el DOCENTE sólo entra por un grupo propio.
        $this->autorizarGrupoSiDocente($grupo);
        $this->grupo = $grupo?->exists ? $grupo : null;
    }

    public function actaCreada(): void
    {
        $this->resetPage();
        session()->flash('success', 'Acta creada correctamente.');
    }

    public function actaCerrada(): void
    {
        $this->resetPage();
        session()->flash('success', 'Acta cerrada correctamente.');
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroPeriodo = '';
        $this->filtroEstatus = '';
        $this->resetPage();
    }

    public function abrirModalCrear(): void
    {
        $this->dispatch('abrir-modal-crear-acta');
    }

    public function cerrar(Acta $acta): void
    {
        Gate::authorize('acta.cerrar', $acta->grupo);

        try {
            $service = app(ActaService::class);
            $service->cerrar($acta);
            $this->dispatch('actaCerrada');
        } catch (\App\Exceptions\ActaException $e) {
            $this->addError('cerrar', $e->getMessage());
        }
    }

    public function render()
    {
        $periodos = Periodo::orderBy('fecha_inicio', 'desc')->get();

        $actas = Acta::with(['grupo.materia', 'periodo', 'cerradaPor'])
            ->when($this->esDocente(), fn ($q) => $q->where('grupo_id', $this->grupo?->id ?? 0))
            ->when($this->busqueda, fn ($q) => $q->whereHas('grupo', fn ($q) => $q->where('clave', 'like', "%{$this->busqueda}%")))
            ->when($this->filtroPeriodo, fn ($q) => $q->where('periodo_id', $this->filtroPeriodo))
            ->when($this->filtroEstatus, fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.academico.acta-admin', [
            'periodos' => $periodos,
            'actas' => $actas,
        ]);
    }
}