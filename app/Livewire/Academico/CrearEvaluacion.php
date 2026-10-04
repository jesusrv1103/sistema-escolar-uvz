<?php

namespace App\Livewire\Academico;

use App\Enums\TipoEvaluacion;
use App\Models\Grupo;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\On;

class CrearEvaluacion extends Component
{
    public bool $modalAbierto = false;

    public ?int $grupoId = null;
    public string $nombre = '';
    public string $tipo = '';
    public int $ponderacion = 0;
    public string $fecha = '';
    public string $descripcion = '';

    #[On('abrir-modal-crear')]
    public function abrirModal(?int $grupoId = null): void
    {
        $this->resetFormulario();
        $this->grupoId = $grupoId;
        $this->fecha = now()->format('Y-m-d');
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->resetFormulario();
    }

    private function resetFormulario(): void
    {
        $this->nombre = '';
        $this->tipo = '';
        $this->ponderacion = 0;
        $this->fecha = '';
        $this->descripcion = '';
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'grupoId' => ['required', 'integer', 'exists:grupos,id'],
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:50'],
            'ponderacion' => ['required', 'integer', 'min:1', 'max:100'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $grupo = \App\Models\Grupo::findOrFail($this->grupoId);

        // Validar ponderación total no supere 100%
        $ponderacionActual = \App\Models\Evaluacion::ponderacionTotalGrupo($this->grupoId);
        if ($this->ponderacion + $ponderacionActual > 100) {
            $this->addError('ponderacion', "La ponderación total del grupo superaría 100% (actual: {$ponderacionActual}%).");
            return;
        }

        try {
            $service = app(\App\Services\EvaluacionService::class);
            $service->crear(
                \App\Models\Grupo::findOrFail($this->grupoId),
                $this->nombre,
                $this->tipo,
                $this->ponderacion,
                $this->fecha,
                $this->descripcion,
                auth()->user()
            );

            session()->flash('success', 'Evaluación creada correctamente.');
            $this->cerrarModal();
            $this->dispatch('evaluacionCreada');
        } catch (\App\Exceptions\EvaluacionException $e) {
            $this->addError('nombre', $e->getMessage());
        }
    }

    public function render()
    {
        $tipos = \App\Enums\TipoEvaluacion::ejemplos();

        return view('livewire.academico.crear-evaluacion', [
            'tipos' => \App\Enums\TipoEvaluacion::ejemplos(),
        ]);
    }
}