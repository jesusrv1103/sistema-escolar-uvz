<?php

namespace App\Livewire\Pagos;

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusPago;
use App\Enums\MetodoPago;
use App\Exceptions\PagoInvalidoException;
use App\Models\Inscripcion;
use App\Services\PagoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Registro de un pago (FC-5, Fase 8): ADMISIONES/ADMINISTRACION capturan
 * (ADMIN pasa por `Gate::before`); CONTROL_ESCOLAR NO (403 server-side).
 *
 * - Selecciona un concepto `PENDIENTE` de la inscripción y muestra su
 *   `monto_total`: el monto NO es editable (un pago completo por concepto,
 *   sin parciales); el valor viaja del servidor, nunca del formulario.
 * - El comprobante es opcional (PDF/JPG/JPEG/PNG ≤ 10 MB) y se guarda en el
 *   disco PRIVADO con validación MIME real dentro de `PagoService`.
 * - La UI no toca estatus, folio ni estados: solo llama al servicio.
 * - Nunca carga ni muestra información médica (RB-18).
 */
class ModalRegistrarPago extends Component
{
    use WithFileUploads;

    public int $inscripcionId;

    public bool $abierto = false;

    public ?int $conceptoId = null;

    public string $fecha_pago = '';

    public string $metodo = '';

    public string $referencia = '';

    public string $notas = '';

    /** @var \Illuminate\Http\UploadedFile|null temporal de Livewire */
    public $comprobante = null;

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
        $this->fecha_pago = today()->toDateString();
    }

    public function abrir(): void
    {
        $this->autorizarLectura();
        $this->reset('comprobante', 'conceptoId', 'referencia', 'notas');
        $this->metodo = '';
        $this->fecha_pago = today()->toDateString();
        $this->resetErrorBag();
        $this->abierto = true;
    }

    public function cerrar(): void
    {
        $this->abierto = false;
        $this->reset('comprobante');
        $this->resetErrorBag();
    }

    public function registrar(): void
    {
        // Autorización server-side (el @can de la vista solo complementa).
        Gate::authorize('pago.registrar');
        $inscripcion = $this->inscripcionOrFail();

        $this->validate([
            'conceptoId' => [
                'required',
                'integer',
                Rule::exists('conceptos_pago', 'id')
                    ->where('inscripcion_id', $inscripcion->getKey())
                    ->where('estatus', 'PENDIENTE'),
            ],
            'fecha_pago' => ['required', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'metodo' => ['required', Rule::in(array_column(MetodoPago::cases(), 'value'))],
            'referencia' => [
                Rule::requiredIf(in_array($this->metodo, [MetodoPago::TRANSFERENCIA->value, MetodoPago::TARJETA->value], true)),
                'nullable',
                'string',
                'max:100',
            ],
            'notas' => ['nullable', 'string', 'max:5000'],
            'comprobante' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [], [
            'conceptoId' => 'concepto',
            'fecha_pago' => 'fecha_pago',
            'comprobante' => 'comprobante',
        ]);

        $concepto = $inscripcion->conceptosPago()->whereKey($this->conceptoId)->first();

        if ($concepto === null) {
            $this->addError('concepto', 'El concepto seleccionado no existe o ya no está pendiente.');

            return;
        }

        try {
            app(PagoService::class)->registrar($concepto, [
                // El monto se toma del servidor (monto_total del concepto):
                // el formulario no lo expone ni lo permite dividir.
                'monto' => $concepto->monto_total,
                'fecha_pago' => $this->fecha_pago,
                'metodo' => $this->metodo,
                'referencia' => $this->referencia,
                'notas' => $this->notas,
                'comprobante' => $this->comprobante,
            ]);
        } catch (PagoInvalidoException $e) {
            $this->addError('pago', $e->getMessage());

            return;
        }

        $this->reset('conceptoId', 'referencia', 'notas', 'comprobante');
        $this->metodo = '';
        $this->resetErrorBag();
        $this->abierto = false;
        session()->flash('wizard', sprintf(
            'Pago registrado correctamente: queda %s de validación por Administración.',
            EstatusPago::PENDIENTE->label(),
        ));
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

        // Conceptos PENDIENTE de esta inscripción (el $0 nace PAGADO: no aparece).
        $conceptos = $inscripcion->conceptosPago()
            ->where('estatus', EstatusConceptoPago::PENDIENTE->value)
            ->orderBy('tipo')
            ->orderBy('numero')
            ->get(['id', 'tipo', 'numero', 'concepto', 'monto_total', 'fecha_vencimiento']);

        return view('livewire.pagos.modal-registrar-pago', [
            'inscripcion' => $inscripcion,
            'conceptos' => $conceptos,
            'puedeRegistrar' => Gate::allows('pago.registrar'),
            'metodos' => MetodoPago::cases(),
            // Nunca se pasa información médica al view (RB-18).
        ]);
    }
}
