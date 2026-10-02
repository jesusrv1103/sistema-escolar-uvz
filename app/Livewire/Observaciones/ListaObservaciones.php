<?php

namespace App\Livewire\Observaciones;

use App\Enums\EstatusObservacion;
use App\Enums\TipoObservacion;
use App\Exceptions\ObservacionInvalidaException;
use App\Models\Inscripcion;
use App\Models\Observacion;
use App\Services\ObservacionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Listado de observaciones de una inscripción (Fase 11, §4.13/§12.9).
 *
 * APPEND-ONLY: crear, listar/ver y resolver. NO existe edición del detalle o
 * del tipo, ni borrado (ni delete, ni forceDelete, ni soft delete).
 *
 * Autorización server-side en cada acción: policy `ObservacionPolicy`
 * conectada al modelo real (las 4 capacidades según §8.2); el `@can` de la
 * vista solo complementa la UX. La creación/resolución SIEMPRE delega en
 * `ObservacionService` (la UI no escribe estatus ni responsables).
 *
 * Privacidad: el view solo recibe observaciones (tipo, detalle, estatus,
 * responsables y fechas) — nunca información médica, CURP, datos bancarios,
 * comprobantes, rutas ni secretos (RB-18).
 */
class ListaObservaciones extends Component
{
    public int $inscripcionId;

    /** Formulario de alta (solo se envía al servicio). */
    public string $tipo = '';

    public string $detalle = '';

    /** Filtros mínimos del listado (opciones del BackedEnum o vacío). */
    public string $filtroEstatus = '';

    public string $filtroTipo = '';

    public function mount(Inscripcion $inscripcion): void
    {
        // Lectura: los 4 roles autenticados con acceso a la inscripción.
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    /** Registra una observación (§12.9): tipo en enum + detalle 5–2000. */
    public function crear(): void
    {
        // Policy conectada al modelo real (server-side; @can solo UX).
        Gate::authorize('crear', Observacion::class);
        $inscripcion = $this->inscripcionOrFail();

        // Normalización previa a la validación: Laravel 12 no incluye una
        // regla `trim` en el validador, así que se aplica aquí (y el
        // servicio vuelve a recortar antes de persistir).
        $this->tipo = trim($this->tipo);
        $this->detalle = trim($this->detalle);

        $this->validate([
            'tipo' => ['required', Rule::enum(TipoObservacion::class)],
            'detalle' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            app(ObservacionService::class)->crear($inscripcion, $this->tipo, $this->detalle);
        } catch (ObservacionInvalidaException $e) {
            $this->addError('detalle', $e->getMessage());

            return;
        }

        $this->reset('tipo', 'detalle');
        $this->resetErrorBag();
        session()->flash('observaciones', 'Observación registrada.');
    }

    /** Resuelve una observación ABIERTA (binding horizontal por inscripción). */
    public function resolver(int $observacionId): void
    {
        $inscripcion = $this->inscripcionOrFail();

        // Solo observaciones DE ESTA inscripción (jamás expedientes ajenos).
        $observacion = $inscripcion->observaciones()->findOrFail($observacionId);

        Gate::authorize('resolver', $observacion);

        try {
            app(ObservacionService::class)->resolver($observacion);
        } catch (ObservacionInvalidaException $e) {
            $this->addError('panel', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        session()->flash('observaciones', 'Observación resuelta.');
    }

    public function render()
    {
        $inscripcion = $this->inscripcionOrFail();

        $consulta = $inscripcion->observaciones()
            ->with(['creadaPor:id,name', 'resueltaPor:id,name'])
            ->orderByDesc('id');

        // Filtros: valores basura se ignoran (nunca llegan a la consulta).
        if (EstatusObservacion::tryFrom($this->filtroEstatus) !== null) {
            $consulta->where('estatus', $this->filtroEstatus);
        }

        if (TipoObservacion::tryFrom($this->filtroTipo) !== null) {
            $consulta->where('tipo', $this->filtroTipo);
        }

        return view('livewire.observaciones.lista-observaciones', [
            'inscripcion' => $inscripcion,
            'observaciones' => $consulta->get(),
            'puedeCrear' => Gate::allows('crear', Observacion::class),
            'puedeResolver' => Gate::allows('resolver', Observacion::class),
            'estatusOpciones' => EstatusObservacion::cases(),
            'tipoOpciones' => TipoObservacion::cases(),
            // Nunca se pasa información médica al view (RB-18).
        ]);
    }

    private function inscripcionOrFail(): Inscripcion
    {
        return Inscripcion::findOrFail($this->inscripcionId);
    }
}
