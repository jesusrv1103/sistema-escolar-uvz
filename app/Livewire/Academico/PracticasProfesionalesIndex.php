<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusPracticaProfesional;
use App\Models\Alumno;
use App\Models\PracticaProfesional;
use App\Services\HistorialAcademicoService;
use App\Services\PracticaProfesionalService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Prácticas Profesionales (2.0-13) — CONTROL_ESCOLAR protagoniza el proceso.
 *
 * `calificacion` es texto libre: no se infiere ninguna escala (pendiente
 * institucional). El avance académico sólo se LEE desde
 * `HistorialAcademicoService`.
 */
#[Layout('layouts.app')]
class PracticasProfesionalesIndex extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroEstatus = '';

    public bool $modalAbierto = false;
    public ?PracticaProfesional $practica = null;
    public ?Alumno $alumno = null;
    public string $empresa = '';
    public ?string $fecha_inicio = null;
    public ?string $fecha_fin = null;
    public ?string $asesor = '';
    public ?string $calificacion = '';

    public function mount(): void
    {
        Gate::authorize('practica.ver');
    }

    public function updated(string $property): void
    {
        if (! in_array($property, ['busqueda', 'filtroEstatus'], true)) {
            return;
        }

        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroEstatus = '';
        $this->resetPage();
    }

    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag(['alumno']);
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('practica.crear');
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(PracticaProfesional $practica): void
    {
        Gate::authorize('practica.actualizar', $practica);

        $this->practica = $practica;
        $this->alumno = $practica->alumno;
        $this->empresa = $practica->empresa;
        $this->fecha_inicio = $practica->fecha_inicio?->format('Y-m-d');
        $this->fecha_fin = $practica->fecha_fin?->format('Y-m-d');
        $this->asesor = $practica->asesor;
        $this->calificacion = $practica->calificacion;
        $this->modalAbierto = true;
    }

    public function resetFormulario(): void
    {
        $this->practica = null;
        $this->alumno = null;
        $this->empresa = '';
        $this->fecha_inicio = null;
        $this->fecha_fin = null;
        $this->asesor = '';
        $this->calificacion = '';
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        // Autorización explícita fuera del try: un 403 es 403, no un error de formulario.
        $this->practica !== null
            ? Gate::authorize('practica.actualizar', $this->practica)
            : Gate::authorize('practica.crear');

        $this->validate([
            'alumno' => 'required',
            'empresa' => 'required|string|max:500',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'asesor' => 'nullable|string|max:500',
            // Texto libre: el sistema no infiere una escala de calificación.
            'calificacion' => 'nullable|string|max:100',
        ]);

        try {
            $datos = [
                'empresa' => $this->empresa,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'asesor' => $this->asesor,
                'calificacion' => $this->calificacion,
            ];

            $service = app(PracticaProfesionalService::class);

            if ($this->practica) {
                $service->actualizar($this->practica, $datos);
                session()->flash('success', 'Prácticas actualizadas correctamente.');
            } else {
                $service->crear($this->alumno, $datos);
                session()->flash('success', 'Prácticas registradas correctamente.');
            }

            $this->modalAbierto = false;
            $this->resetFormulario();
            $this->dispatch('practicaGuardada');
        } catch (\Throwable $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function validarRegistro(PracticaProfesional $practica): void
    {
        try {
            app(PracticaProfesionalService::class)->validar($practica);
            session()->flash('success', 'Prácticas validadas correctamente.');
            $this->dispatch('practicaGuardada');
        } catch (\Throwable $e) {
            $this->addError('validar', $e->getMessage());
        }
    }

    public function render(HistorialAcademicoService $historial)
    {
        $registros = PracticaProfesional::with(['alumno', 'validadoPor'])
            ->when($this->busqueda !== '', fn ($q) => $q->whereHas('alumno', fn ($w) => $w->where(
                fn ($s) => $s->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                    ->orWhere('numero_control', 'like', "%{$this->busqueda}%")
            )))
            ->when($this->filtroEstatus !== '', fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->orderByDesc('created_at')
            ->paginate(15);

        $avanceAlumnos = collect($registros->items())
            ->mapWithKeys(fn (PracticaProfesional $p) => [$p->alumno_id => $p->alumno
                ? $historial->porcentajeAvance($p->alumno)
                : 0.0]);

        return view('livewire.academico.practicas-profesionales-index', [
            'registros' => $registros,
            'avanceAlumnos' => $avanceAlumnos,
            'avanceSeleccionado' => $this->alumno ? $historial->porcentajeAvance($this->alumno) : null,
            'estatus' => EstatusPracticaProfesional::cases(),
        ]);
    }
}
