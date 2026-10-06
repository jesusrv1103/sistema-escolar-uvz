<?php

namespace App\Livewire\Academico;

use App\Enums\EstatusServicioSocial;
use App\Models\Alumno;
use App\Models\ServicioSocial;
use App\Services\HistorialAcademicoService;
use App\Services\ServicioSocialService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Servicio Social (2.0-13) — CONTROL_ESCOLAR protagoniza el proceso.
 *
 * Consulta/navegación + alta/edición/validación mediante `ServicioSocialService`
 * (autorización server-side, transacción y auditoría). Sólo se LEE el avance
 * académico del alumno desde `HistorialAcademicoService` (fuente única).
 */
#[Layout('layouts.app')]
class ServicioSocialIndex extends Component
{
    use WithPagination;

    public string $busqueda = '';
    public string $filtroEstatus = '';

    public bool $modalAbierto = false;
    public ?ServicioSocial $servicio = null;
    public ?Alumno $alumno = null;
    public string $actividad = '';
    public string $dependencia = '';
    public ?string $fecha_inicio = null;
    public ?string $fecha_fin = null;
    public int $avance = 0;

    public function mount(): void
    {
        Gate::authorize('servicio_social.ver');
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

    /**
     * Selección del alumno desde el combo: `alumno` es modelo tipado y
     * `wire:model` enviaría el id como texto (TypeError).
     */
    public function seleccionarAlumno(string $alumnoId): void
    {
        $this->alumno = $alumnoId !== '' ? Alumno::find($alumnoId) : null;
        $this->resetErrorBag(['alumno']);
    }

    public function abrirModalCrear(): void
    {
        Gate::authorize('servicio_social.crear');
        $this->resetFormulario();
        $this->modalAbierto = true;
    }

    public function abrirModalEditar(ServicioSocial $servicio): void
    {
        Gate::authorize('servicio_social.actualizar', $servicio);

        $this->servicio = $servicio;
        $this->alumno = $servicio->alumno;
        $this->actividad = $servicio->actividad;
        $this->dependencia = $servicio->dependencia;
        $this->fecha_inicio = $servicio->fecha_inicio?->format('Y-m-d');
        $this->fecha_fin = $servicio->fecha_fin?->format('Y-m-d');
        $this->avance = $servicio->avance;
        $this->modalAbierto = true;
    }

    public function resetFormulario(): void
    {
        $this->servicio = null;
        $this->alumno = null;
        $this->actividad = '';
        $this->dependencia = '';
        $this->fecha_inicio = null;
        $this->fecha_fin = null;
        $this->avance = 0;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        $this->validate([
            'alumno' => 'required',
            'actividad' => 'required|string|max:500',
            'dependencia' => 'required|string|max:500',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'avance' => 'required|integer|min:0|max:100',
        ]);

        try {
            $service = app(ServicioSocialService::class);
            $datos = [
                'actividad' => $this->actividad,
                'dependencia' => $this->dependencia,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
            ];

            if ($this->servicio) {
                $service->actualizar($this->servicio, $datos);

                if ($this->avance !== $this->servicio->refresh()->avance) {
                    $service->registrarAvance($this->servicio, $this->avance);
                }

                session()->flash('success', 'Servicio Social actualizado correctamente.');
            } else {
                $service->crear($this->alumno, $datos);
                session()->flash('success', 'Servicio Social registrado correctamente.');
            }

            $this->modalAbierto = false;
            $this->resetFormulario();
            $this->dispatch('servicioSocialGuardado');
        } catch (\Throwable $e) {
            $this->addError('guardar', $e->getMessage());
        }
    }

    public function validarRegistro(ServicioSocial $servicio): void
    {
        try {
            app(ServicioSocialService::class)->validar($servicio);
            session()->flash('success', 'Servicio Social validado correctamente.');
            $this->dispatch('servicioSocialGuardado');
        } catch (\Throwable $e) {
            $this->addError('validar', $e->getMessage());
        }
    }

    public function render(HistorialAcademicoService $historial)
    {
        $registros = ServicioSocial::with(['alumno', 'validadoPor'])
            ->when($this->busqueda !== '', fn ($q) => $q->whereHas('alumno', fn ($w) => $w->where(
                fn ($s) => $s->where('nombre', 'like', "%{$this->busqueda}%")
                    ->orWhere('apellidos', 'like', "%{$this->busqueda}%")
                    ->orWhere('numero_control', 'like', "%{$this->busqueda}%")
            )))
            ->when($this->filtroEstatus !== '', fn ($q) => $q->where('estatus', $this->filtroEstatus))
            ->orderByDesc('created_at')
            ->paginate(15);

        // Sólo LECTURA del avance académico: fuente única HistorialAcademicoService.
        $avanceAlumnos = collect($registros->items())
            ->mapWithKeys(fn (ServicioSocial $s) => [$s->alumno_id => $s->alumno
                ? $historial->porcentajeAvance($s->alumno)
                : 0.0]);

        return view('livewire.academico.servicio-social-index', [
            'registros' => $registros,
            'avanceAlumnos' => $avanceAlumnos,
            'avanceSeleccionado' => $this->alumno ? $historial->porcentajeAvance($this->alumno) : null,
            'estatus' => EstatusServicioSocial::cases(),
        ]);
    }
}
