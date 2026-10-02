<?php

namespace App\Livewire\Inscripciones;

use App\Enums\InscripcionEstatus;
use App\Exceptions\CancelacionInvalidaException;
use App\Models\Inscripcion;
use App\Services\InscripcionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Cancelación de la inscripción (FC-8/RB-11, Fase 13) — UI mínima.
 *
 * - Solo `inscripcion.cancelar` (ADMIN) ve la acción (`Gate::allows` en el
 *   render + `Gate::authorize` server-side en la acción: el `@can`/UI nunca
 *   sustituye la autorización).
 * - Solo se ofrece en estados CANCELABLES (no terminales: `ENROLLED` y
 *   `CANCELLED` no la permiten).
 * - El motivo se solicita ANTES de confirmar (trim + obligatorio, ≤ 2000).
 * - La lógica vive en `InscripcionService::cancelar()` (único punto): aquí
 *   solo se autoriza, se traduce la excepción en error de formulario y se
 *   avisa de que la cancelación es definitiva en el MVP, sin reembolso y
 *   conservando los pagos validados.
 * - Nunca carga ni muestra información médica (RB-18).
 */
class ModalCancelarInscripcion extends Component
{
    public int $inscripcionId;

    public bool $abierto = false;

    public string $motivo = '';

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    public function abrir(): void
    {
        $this->autorizarLectura();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->reset('motivo');
        $this->resetErrorBag();
    }

    public function cancelar(): void
    {
        // Autorización server-side: un rol sin capacidad recibe 403 aunque
        // fuerce la llamada Livewire (el botón oculto es solo UX).
        Gate::authorize('inscripcion.cancelar');

        // Laravel 12 no trims solo: trim previo (patrón Fase 11) para que
        // un motivo de solo espacios caiga en la validación del campo.
        $this->motivo = trim($this->motivo);
        $this->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ], [], [
            'motivo' => 'motivo',
        ]);

        try {
            app(InscripcionService::class)->cancelar($this->inscripcionOrFail(), $this->motivo);
            $this->reset('motivo');
            $this->resetErrorBag();
            $this->abierto = false;
            session()->flash('wizard', 'Inscripción cancelada (CANCELLED): es definitiva en el MVP, no implica reembolso y los pagos validados permanecen registrados.');
        } catch (CancelacionInvalidaException $e) {
            $this->addError('cancelacion', $e->getMessage());
        }
    }

    private function autorizarLectura(): void
    {
        Gate::authorize('view', $this->inscripcionOrFail());
    }

    private function inscripcionOrFail(): Inscripcion
    {
        return Inscripcion::findOrFail($this->inscripcionId);
    }

    public function render()
    {
        $inscripcion = $this->inscripcionOrFail();

        return view('livewire.inscripciones.modal-cancelar-inscripcion', [
            'inscripcion' => $inscripcion,
            'puedeCancelar' => Gate::allows('inscripcion.cancelar'),
            'cancelable' => $inscripcion->estatus->puedeIrA(InscripcionEstatus::CANCELLED),
        ]);
    }
}
