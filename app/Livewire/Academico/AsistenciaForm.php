<?php

namespace App\Livewire\Academico;

use App\Models\Asistencia;
use App\Models\InscripcionMateria;
use App\Services\AsistenciaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AsistenciaForm extends Component
{
    public bool $modalAbierto = false;
    public ?InscripcionMateria $inscripcion = null;
    public string $fecha = '';
    public bool $presente = true;
    public ?Asistencia $asistenciaExistente = null;

    protected $listeners = ['abrirModalAsistencia' => 'abrirModal'];

    public function abrirModal(InscripcionMateria $inscripcion, string $fecha = ''): void
    {
        Gate::authorize('asistencia.crear');

        $this->inscripcion = $inscripcion->load('matriculaPeriodo.alumno');
        $this->fecha = $fecha ?: now()->format('Y-m-d');
        $this->presente = true;
        $this->asistenciaExistente = null;

        $existente = Asistencia::where('inscripcion_materia_id', $inscripcion->id)
            ->where('fecha', $this->fecha)
            ->first();

        if ($existente) {
            $this->asistenciaExistente = $existente;
            $this->presente = $existente->presente;
        }

        $this->modalAbierto = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'fecha' => 'required|date',
            'presente' => 'boolean',
        ]);

        if (!$this->inscripcion) {
            $this->addError('inscripcion', 'No hay inscripción seleccionada');
            return;
        }

        try {
            $service = app(AsistenciaService::class);
            $service->registrar(
                inscripcion: $this->inscripcion,
                fecha: $this->fecha,
                presente: $this->presente,
                usuario: auth()->user(),
            );

            $this->modalAbierto = false;
            $this->reset(['inscripcion', 'fecha', 'presente', 'asistenciaExistente']);
            $this->dispatch('asistenciaRegistrada');
        } catch (\App\Exceptions\AsistenciaException $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.academico.asistencia-form');
    }
}