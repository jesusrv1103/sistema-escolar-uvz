<?php

namespace App\Livewire\Pagos;

use App\Exceptions\PagoInvalidoException;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Services\PagoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Revisión de pagos (FC-5, Fase 8): four-eyes — solo ADMINISTRACION valida
 * o rechaza (gate `pago.validar`; ADMIN pasa por `Gate::before`); ADMISIONES,
 * CONTROL_ESCOLAR y el resto solo LEEN.
 *
 * - Muestra los pagos `PENDIENTE` de la inscripción con los datos mínimos
 *   para decidir: alumno, programa, concepto, monto, método, referencia,
 *   fecha y el comprobante cargado (descarga privada autorizada).
 * - Validar emite el folio único y liquida el concepto; rechazar exige
 *   motivo (≤ 2000) y deja el intento como historial sin folio.
 * - La UI no modifica estatus/folio/estados: todo pasa por `PagoService`.
 * - Nunca carga ni muestra información médica (RB-18).
 */
class ModalValidarPago extends Component
{
    public int $inscripcionId;

    public bool $abierto = false;

    /** Pago seleccionado para rechazo (flujo con motivo obligatorio). */
    public ?int $pagoRechazoId = null;

    public string $motivo = '';

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    public function abrir(): void
    {
        $this->autorizarLectura();
        $this->reset('pagoRechazoId', 'motivo');
        $this->resetErrorBag();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->reset('pagoRechazoId', 'motivo');
        $this->resetErrorBag();
    }

    public function validar(int $pagoId): void
    {
        Gate::authorize('pago.validar');

        $pago = $this->pagoDeLaInscripcion($pagoId);

        if ($pago === null) {
            $this->addError('pago', 'El pago seleccionado no existe.');

            return;
        }

        try {
            app(PagoService::class)->validar($pago);
            $this->resetErrorBag();
            session()->flash('wizard', 'Pago validado: folio emitido y concepto liquidado.');
        } catch (PagoInvalidoException $e) {
            $this->addError('pago', $e->getMessage());
        }
    }

    public function seleccionarRechazo(int $pagoId): void
    {
        Gate::authorize('pago.validar');
        $this->autorizarLectura();

        if ($this->pagoDeLaInscripcion($pagoId) === null) {
            $this->addError('pago', 'El pago seleccionado no existe.');

            return;
        }

        $this->pagoRechazoId = $pagoId;
        $this->reset('motivo');
        $this->resetErrorBag();
    }

    public function rechazar(): void
    {
        Gate::authorize('pago.validar');

        $this->validate([
            'motivo' => ['required', 'string', 'max:2000'],
        ], [], [
            'motivo' => 'motivo',
        ]);

        $pago = $this->pagoDeLaInscripcion($this->pagoRechazoId ?? 0);

        if ($pago === null) {
            $this->addError('pago', 'Selecciona un pago para rechazar.');

            return;
        }

        try {
            app(PagoService::class)->rechazar($pago, $this->motivo);
            $this->reset('pagoRechazoId', 'motivo');
            $this->resetErrorBag();
            session()->flash('wizard', 'Pago rechazado: el concepto sigue PENDIENTE y admite un nuevo intento.');
        } catch (PagoInvalidoException $e) {
            // p. ej. motivo solo espacios: se traduce al campo motivo.
            $this->addError('motivo', $e->getMessage());
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

    /** Solo pagos PENDIENTE que pertenezcan a ESTA inscripción (nunca ajenos). */
    private function pagoDeLaInscripcion(int $pagoId): ?Pago
    {
        return Pago::query()
            ->pendiente()
            ->where('concepto_pago_id', function ($consulta): void {
                $consulta->select('id')
                    ->from('conceptos_pago')
                    ->where('inscripcion_id', $this->inscripcionId);
            })
            ->whereKey($pagoId)
            ->first();
    }

    public function render()
    {
        $inscripcion = $this->inscripcionOrFail();

        $pagos = Pago::query()
            ->pendiente()
            ->whereHas('conceptoPago.inscripcion', function ($consulta): void {
                $consulta->where('id', $this->inscripcionId);
            })
            ->with([
                'conceptoPago',
                'conceptoPago.inscripcion.alumno',
                'conceptoPago.inscripcion.programa',
                'registradoPor',
            ])
            ->orderBy('id')
            ->get();

        return view('livewire.pagos.modal-validar-pago', [
            'inscripcion' => $inscripcion,
            'pagos' => $pagos,
            'puedeValidar' => Gate::allows('pago.validar'),
            // Nunca se pasa información médica al view (RB-18).
        ]);
    }
}
