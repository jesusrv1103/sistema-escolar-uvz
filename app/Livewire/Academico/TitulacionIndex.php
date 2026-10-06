<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusTitulacion;
use App\Models\Alumno;
use App\Models\Titulacion;
use App\Services\TitulacionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Titulación interna (2.0-14) — CONTROL_ESCOLAR gestiona el expediente.
 *
 * Exige egreso registrado (se selecciona entre alumnos egresados). Modalidad
 * y folio interno son captura libre: no se infiere catálogo ni folio
 * automático (pendientes institucionales, §J del análisis del macrobloque).
 */
#[Layout('layouts.app')]
class TitulacionIndex extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroEstatus = '';

    public bool $modalAbierto = false;
    public ?Titulacion $titulacion = null;
    public ?Alumno $alumno = null;
    public ?string $modalidad = '';
    public ?string $folio_interno = '';
    public string $estatus = EstatusTitulacion::EN_TRAMITE->value;

    public function mount(): void
    {
        Gate::authorize('titulacion.ver');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['busqueda', 'filtroEstatus'], true)) {
            $this->resetPage();
        }
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
        Gate::authorize('titulacion.crear');
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(Titulacion $titulacion): void
    {
        Gate::authorize('titulacion.actualizar', $titulacion);

        $this->titulacion = $titulacion;
        $this->alumno = $titulacion->alumno;
        $this->modalidad = $titulacion->modalidad;
        $this->folio_interno = $titulacion->folio_interno;
        $this->estatus = $titulacion->estatus->value;
        $this->modalAbierto = true;
    }

    public function resetFormulario(): void
    {
        $this->titulacion = null;
        $this->alumno = null;
        $this->modalidad = '';
        $this->folio_interno = '';
        $this->estatus = EstatusTitulacion::EN_TRAMITE->value;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        // Autorización explícita fuera del try: un 403 es 403, no un error de formulario.
        $this->titulacion !== null
            ? Gate::authorize('titulacion.actualizar', $this->titulacion)
            : Gate::authorize('titulacion.crear');

        $this->validate([
            'alumno' => 'required',
            'modalidad' => 'nullable|string|max:200',
            'folio_interno' => 'nullable|string|max:100',
            'estatus' => ['required', 'in:' . implode(',', array_column(EstatusTitulacion::cases(), 'value'))],
        ]);

        try {
            $datos = [
                'modalidad' => $this->modalidad,
                'folio_interno' => $this->folio_interno,
                'estatus' => $this->estatus,
            ];

            $service = app(TitulacionService::class);

            if ($this->titulacion) {
                $service->actualizar($this->titulacion, $datos);
                session()->flash('success', 'Expediente de titulación actualizado correctamente.');
            } else {
                $service->crear($this->alumno, $datos);
                session()->flash('success', 'Expediente de titulación registrado correctamente.');
            }

            $this->modalAbierto = false;
            $this->resetFormulario();
            $this->dispatch('titulacionGuardada');
        } catch (\Throwable $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function render()
    {
        $titulaciones = Titulacion::with(['alumno', 'egreso.planVersion.plan', 'registradoPor'])
            ->when($this->busqueda !== '', fn ($q) => $q->whereHas('alumno', fn ($w) => $w->where(
                fn ($s) => $s->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                    ->orWhere('numero_control', 'like', "%{$this->busqueda}%")
            )))
            ->when($this->filtroEstatus !== '', fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->orderByDesc('registrado_at')
            ->paginate(15);

        // Sólo alumnos con egreso registrado pueden iniciar titulación.
        $alumnosEgresados = \App\Models\Egreso::with('alumno')
            ->get()
            ->map->alumno
            ->filter()
            ->sortBy('apellidos')
            ->values();

        return view('livewire.academico.titulacion-index', [
            'titulaciones' => $titulaciones,
            'alumnosEgresados' => $alumnosEgresados,
            'estatuses' => EstatusTitulacion::cases(),
        ]);
    }
}
