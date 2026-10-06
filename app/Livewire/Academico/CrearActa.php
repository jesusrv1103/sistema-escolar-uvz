<?php

namespace App\Livewire\Academico;

use App\Livewire\Concerns\AutorizaGrupoDocente;
use App\Models\Acta;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Services\ActaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CrearActa extends Component
{
    use AutorizaGrupoDocente;

    public bool $modalAbierto = false;
    public ?Grupo $grupo = null;
    public ?Periodo $periodo = null;

    protected $listeners = ['abrir-modal-crear-acta' => 'abrirModal'];

    public function abrirModal(): void
    {
        Gate::authorize('acta.crear');

        $this->grupo = null;
        $this->periodo = null;
        $this->modalAbierto = true;
    }

    /**
     * Selección desde los combos del modal.
     *
     * No puede ser `wire:model`: `periodo` y `grupo` son modelos tipados y
     * Livewire recibe el id como texto (TypeError "Cannot assign string"). El
     * id llega a la acción y Blade marca la opción con `@selected`.
     */
    public function seleccionarPeriodo(string $periodoId): void
    {
        $this->periodo = $periodoId !== '' ? Periodo::find($periodoId) : null;
        // El listado de grupos depende del periodo: la selección anterior deja de existir.
        $this->grupo = null;
        $this->resetErrorBag();
    }

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

    public function crear(): void
    {
        $this->validate([
            'grupo' => 'required',
            'periodo' => 'required',
        ]);

        if (!$this->grupo || !$this->periodo) {
            return;
        }

        // Validar que el grupo pertenezca al periodo
        if ($this->grupo->periodo_id !== $this->periodo->id) {
            $this->addError('grupo', 'El grupo seleccionado no pertenece al periodo elegido.');
            return;
        }

        try {
            $service = app(ActaService::class);
            $service->crear(
                grupo: $this->grupo,
                usuario: auth()->user(),
            );

            $this->modalAbierto = false;
            $this->reset(['grupo', 'periodo']);
            $this->dispatch('actaCreada');
        } catch (\App\Exceptions\ActaException $e) {
            $this->addError('crear', $e->getMessage());
        }
    }

    public function render()
    {
        $periodos = Periodo::where('activo', true)->orderBy('fecha_inicio', 'desc')->get();
        $grupos = $this->limitarGruposAlDocente(
            Grupo::with(['materia', 'periodo', 'docente'])
                ->where('activo', true)
                ->where('periodo_id', $this->periodo?->id ?? 0)
        )->orderBy('clave')->get();

        return view('livewire.academico.crear-acta', [
            'periodos' => $periodos,
            'grupos' => $grupos,
        ]);
    }
}