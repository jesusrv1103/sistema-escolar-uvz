<?php

namespace App\Livewire\Academico;

use App\Models\Alumno;
use App\Models\Periodo;
use App\Services\HistorialAcademicoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class HistorialAcademico extends Component
{
    use WithPagination;

    public ?Alumno $alumno = null;
    public string $busqueda = '';
    public string $filtroPeriodo = '';

    protected $listeners = ['alumnoSeleccionado'];

    public function mount(): void
    {
        // Aislamiento de portales (2.0-15): esta pantalla no tenía ninguna
        // autorización server-side (cualquier autenticado podía abrirla).
        Gate::authorize('historial.ver');
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroPeriodo = '';
    }

    public function alumnoSeleccionado(Alumno $alumno): void
    {
        $this->alumno = $alumno;
        $this->resetPage();
    }

    public function render()
    {
        $alumnos = Alumno::with(['matriculas.periodo', 'matriculas.programa'])
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