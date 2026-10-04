<?php

namespace App\Livewire\Academico;

use App\Models\Evaluacion;
use App\Services\EvaluacionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\On;

class EditarEvaluacion extends Component
{
    public bool $modalAbierto = false;
    public ?Evaluacion $evaluacion = null;

    public string $nombre = '';
    public string $tipo = '';
    public int $ponderacion = 0;
    public string $fecha = '';
    public string $descripcion = '';

    #[On('abrir-modal-editar')]
    public function abrirModal(int $evaluacionId): void
    {
        $this->evaluacion = \App\Models\Evaluacion::findOrFail($evaluacionId);

        Gate::authorize('evaluacion.actualizar', $this->evaluacion);

        if (!$this->evaluacion->esBorrador()) {
            $this->addError('evaluacion', 'Solo se pueden modificar evaluaciones en estado BORRADOR.');
            return;
        }

        $this->nombre = $this->evaluacion->nombre;
        $this->tipo = $this->evaluacion->tipo;
        $this->ponderacion = $this->evaluacion->ponderacion;
        $this->fecha = $this->evaluacion->fecha?->format('Y-m-d') ?? '';
        $this->descripcion = $this->evaluacion->descripcion ?? '';
        $this->modalAbierto = true;
    }

    public function cerrarModal(): void
    {
        $this->modalAbierto = false;
        $this->evaluacion = null;
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
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'max:50'],
            'ponderacion' => ['required', 'integer', 'min:1', 'max:100'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        // Validar ponderación total no supere 100% (excluyendo la actual)
        $grupo = $this->evaluacion->grupo;
        $ponderacionActual = \App\Models\Evaluacion::ponderacionTotalGrupo($this->evaluacion->grupo_id);
        $ponderacionSinActual = $ponderacionActual - $this->evaluacion->ponderacion;
        $nuevaTotal = $ponderacionActual - $this->evaluacion->ponderacion + $this->ponderacion;

        if ($nuevaTotal > 100) {
            $this->addError('ponderacion', "La ponderación total del grupo superaría 100% (actual sin esta: " . ($ponderacionActual - $this->evaluacion->ponderacion) . "%, nueva: {$this->ponderacion}%).");
            return;
        }

        try {
            $service = app(\App\Services\EvaluacionService::class);
            $service->actualizar(
                $this->evaluacion,
                $this->nombre,
                $this->tipo,
                $this->ponderacion,
                $this->fecha,
                $this->descripcion,
                auth()->user()
            );

            session()->flash('success', 'Evaluación actualizada correctamente.');
            $this->cerrarModal();
            $this->dispatch('evaluacionActualizada');
        } catch (\App\Exceptions\EvaluacionException $e) {
            $this->addError('nombre', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.academico.editar-evaluacion', [
            'tipos' => \App\Enums\TipoEvaluacion::cases(),
        ]);
    }
}