<?php

namespace App\Livewire\Academico;

use App\Models\Alumno;
use App\Models\Periodo;
use App\Services\HistorialAcademicoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class HistorialAcademico extends Component
{
    public ?Alumno $alumno = null;
    public string $busqueda = '';

    protected $listeners = ['alumnoSeleccionado'];

    public function alumnoSeleccionado(Alumno $alumno): void
    {
        $this->alumno = $alumno;
        $this->resetPage();
    }

    public function render()
    {
        $alumnos = Alumno::with(['matriculasPeriodo.periodo', 'matriculasPeriodo.programa'])
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                ->orWhere('curp', 'like', "%{$this->busqueda}%")
                ->orWhere('numero_control', 'like', "%{$this->busqueda}%"))
            ->orderBy('apellidos')
            ->orderBy('nombre')
            ->paginate(15);

        $periodos = Periodo::orderBy('fecha_inicio', 'desc')->get();

        return view('livewire.academico.historial-academico', [
            'alumnos' => $alumnos,
            'periodos' => $periodos,
        ]);
    }
}