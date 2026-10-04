<?php

namespace App\Livewire\Academico;

use App\Models\Acta;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Services\ActaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CrearActa extends Component
{
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
        $grupos = Grupo::with(['materia', 'periodo', 'docente'])
            ->where('activo', true)
            ->where('periodo_id', $this->periodo?->id ?? 0)
            ->orderBy('clave')
            ->get();

        return view('livewire.academico.crear-acta', [
            'periodos' => $periodos,
            'grupos' => $grupos,
        ]);
    }
}