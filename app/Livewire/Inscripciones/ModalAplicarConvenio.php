<?php

namespace App\Livewire\Inscripciones;

use App\Exceptions\ConvenioInvalidoException;
use App\Models\Convenio;
use App\Models\Inscripcion;
use App\Services\ConvenioService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Modal de convenio de la inscripción (FC-3).
 *
 * - Muestra el estado del convenio (pendiente de aprobación / aprobado /
 *   rechazado) y los convenios **activos, no eliminados y vigentes**
 *   con la información mínima: nombre, tipo y valor.
 * - La lógica vive en `ConvenioService`; aquí solo se autoriza la lectura y
 *   se traducen las excepciones de negocio en errores del formulario.
 * - Four-eyes server-side: los gates `convenio.aplicar` / `convenio.aprobar`
 *   se validan dentro del servicio (ADMISIONES no aprueba → 403, F24).
 * - Nunca carga ni muestra información médica (RB-18).
 */
class ModalAplicarConvenio extends Component
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
        $this->resetErrorBag();
    }

    public function aplicar(int $convenioId): void
    {
        $this->autorizarLectura();

        $convenio = Convenio::find($convenioId);

        if ($convenio === null) {
            $this->addError('convenio', 'El convenio seleccionado no existe.');

            return;
        }

        try {
            app(ConvenioService::class)->aplicar($this->inscripcionOrFail(), $convenio);
            $this->abierto = false;
            session()->flash('wizard', 'Convenio aplicado: queda pendiente de aprobación por Administración.');
        } catch (ConvenioInvalidoException $e) {
            $this->addError('convenio', $e->getMessage());
        }
    }

    public function aprobar(): void
    {
        $this->autorizarLectura();

        try {
            app(ConvenioService::class)->aprobar($this->inscripcionOrFail());
            session()->flash('wizard', 'Convenio aprobado: descuento congelado sobre el concepto de inscripción.');
        } catch (ConvenioInvalidoException $e) {
            $this->addError('convenio', $e->getMessage());
        }
    }

    public function rechazar(): void
    {
        $this->autorizarLectura();

        $this->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], [
            'motivo' => 'motivo',
        ]);

        try {
            app(ConvenioService::class)->rechazar($this->inscripcionOrFail(), $this->motivo);
            $this->reset('motivo');
            $this->resetErrorBag();
            session()->flash('wizard', 'Convenio rechazado: la inscripción queda sin descuento.');
        } catch (ConvenioInvalidoException $e) {
            $this->addError('convenio', $e->getMessage());
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

        // Solo convenios activos, no eliminados y vigentes hoy (RB-13).
        $hoy = today();
        $convenios = Convenio::query()
            ->where('activo', true)
            ->where(function ($consulta) use ($hoy): void {
                $consulta->whereNull('fecha_inicio')->orWhere('fecha_inicio', '<=', $hoy);
            })
            ->where(function ($consulta) use ($hoy): void {
                $consulta->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $hoy);
            })
            ->orderBy('nombre')
            ->get(['id', 'clave', 'nombre', 'tipo', 'valor', 'fecha_inicio', 'fecha_fin']);

        return view('livewire.inscripciones.modal-aplicar-convenio', [
            'inscripcion' => $inscripcion,
            'convenios' => $convenios,
            'puedeAplicar' => Gate::allows('convenio.aplicar'),
            'puedeAprobar' => Gate::allows('convenio.aprobar'),
        ]);
    }
}
