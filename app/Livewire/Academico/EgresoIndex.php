<?php

namespace App\Livewire\Academico;

use App\Models\Alumno;
use App\Models\Egreso;
use App\Services\EgresoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Egreso interno (2.0-14) — CONTROL_ESCOLAR verifica y registra.
 *
 * La verificación es una LECTURA de los requisitos evaluables con datos
 * existentes (plan, materias, créditos, servicio social, prácticas y bajas).
 * El egreso sólo se registra cuando TODO está cumplido; la existencia del
 * registro es el estado "EGRESÓ". La deuda no es requisito de egreso.
 */
#[Layout('layouts.app')]
class EgresoIndex extends Component
{
    use WithPagination;

    /** Filtro de texto sobre la tabla de egresados. */
    public string $busqueda = '';

    /** Alumno consultado para ver su situación de egreso. */
    public ?Alumno $alumno = null;

    public function mount(): void
    {
        Gate::authorize('egreso.ver');
    }

    public function updated(string $property): void
    {
        if ($property === 'busqueda') {
            $this->resetPage();
        }
    }

    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag();
    }

    public function registrarEgreso(): void
    {
        if ($this->alumno === null) {
            $this->addError('registrar', 'Selecciona primero un alumno.');

            return;
        }

        // Autorización explícita fuera del try: un 403 es 403, no un error de formulario.
        Gate::authorize('egreso.crear');

        try {
            app(EgresoService::class)->registrar($this->alumno);

            session()->flash('success', 'Egreso registrado correctamente.');
            $this->dispatch('egresoRegistrado');
        } catch (\Throwable $e) {
            $this->addError('registrar', $e->getMessage());
        }
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->resetPage();
    }

    public function render(EgresoService $service)
    {
        $verificacion = $this->alumno !== null
            ? $service->verificar($this->alumno)
            : null;

        $egresados = Egreso::with(['alumno', 'planVersion.plan', 'titulacion'])
            ->when($this->busqueda !== '', fn ($q) => $q->whereHas('alumno', fn ($w) => $w->where(
                fn ($s) => $s->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                    ->orWhere('numero_control', 'like', "%{$this->busqueda}%")
            )))
            ->orderByDesc('registrado_at')
            ->paginate(15);

        return view('livewire.academico.egreso-index', [
            'verificacion' => $verificacion,
            'egresados' => $egresados,
        ]);
    }
}
