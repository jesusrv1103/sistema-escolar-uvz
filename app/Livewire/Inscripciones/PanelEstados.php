<?php

namespace App\Livewire\Inscripciones;

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusConvenio;
use App\Enums\EstatusDocumento;
use App\Enums\InscripcionEstatus;
use App\Enums\EstatusObservacion;
use App\Enums\TipoObservacion;
use App\Exceptions\FinalizacionInvalidaException;
use App\Exceptions\NumeroControlException;
use App\Models\Inscripcion;
use App\Models\TipoDocumento;
use App\Services\DocumentoService;
use App\Services\InscripcionService;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Panel de revisión final (FC-7, Fase 12): requisitos RB-8, número de
 * control actual y cierre de la inscripción → `ENROLLED`.
 *
 * - Muestra alumno, programa, periodo, estado actual, pago de `INSCRIPCION`,
 *   convenio, documentos obligatorios (aceptados/pendientes/rechazados/
 *   ausentes), faltantes abiertos y el `numero_control` si ya existe.
 * - NO muestra mensualidades como requisito (RB-8 corregida: no bloquean).
 * - NO incluye información médica (RB-18): no es necesaria para la UI
 *   mínima de la revisión final.
 * - El botón Finalizar solo aparece cuando rol + estado + guardas lo
 *   permiten, pero las guardas SIEMPRE viven server-side en
 *   `InscripcionService::finalizar()`: forzar la llamada Livewire también
 *   falla (sin confiar en el botón oculto).
 */
class PanelEstados extends Component
{
    public int $inscripcionId;

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    /** Finaliza la inscripción (server-side, único punto: el servicio). */
    public function finalizar(): void
    {
        // Autorización server-side (§8.2): ADMIN/CONTROL_ESCOLAR; el @can de
        // la vista solo complementa la UX.
        Gate::authorize('inscripcion.finalizar');
        $inscripcion = $this->inscripcionOrFail();

        try {
            $finalizada = app(InscripcionService::class)->finalizar($inscripcion);
            $numero = $finalizada->alumno->numero_control;
        } catch (FinalizacionInvalidaException | NumeroControlException $e) {
            // Guardas RB-8 reales también cuando la llamada se fuerza.
            $this->addError('panel', $e->getMessage());

            return;
        }

        $this->resetErrorBag();
        session()->flash('revision', sprintf('Inscripción completada. Número de control: %s', $numero));
    }

    public function render()
    {
        $inscripcion = $this->inscripcionOrFail();
        $concepto = $inscripcion->conceptoInscripcion;

        // Documentos obligatorios aplicables (obligatorio + activo + no
        // eliminados) con su desglose para indicar QUÉ falta.
        $obligatorios = TipoDocumento::query()
            ->where('obligatorio', true)
            ->where('activo', true)
            ->pluck('id');

        $docsRequeridos = $inscripcion->documentos()
            ->whereIn('tipo_documento_id', $obligatorios)
            ->get();

        $documentoEn = fn (EstatusDocumento $estatus): int => $docsRequeridos
            ->filter(fn ($documento): bool => $documento->estatus === $estatus)
            ->count();

        $documentos = [
            'total' => $obligatorios->count(),
            'aceptados' => $documentoEn(EstatusDocumento::ACEPTADO),
            'pendientes' => $documentoEn(EstatusDocumento::PENDIENTE),
            'rechazados' => $documentoEn(EstatusDocumento::RECHAZADO),
            'ausentes' => max($obligatorios->count() - $docsRequeridos->count(), 0),
        ];

        $faltantesAbiertos = $inscripcion->observaciones()
            ->where('tipo', TipoObservacion::FALTANTE->value)
            ->where('estatus', EstatusObservacion::ABIERTA->value)
            ->count();

        // Guardas RB-8 en su forma reutilizable (la misma que bloquea
        // server-side): la lista de lo que falta.
        $requisitos = app(InscripcionService::class)->requisitosPendientes($inscripcion);
        $enRevisionFinal = $inscripcion->estatus === InscripcionEstatus::FINAL_REVIEW;

        return view('livewire.inscripciones.panel-estados', [
            'inscripcion' => $inscripcion,
            'alumno' => $inscripcion->alumno,
            'programa' => $inscripcion->programa,
            'periodo' => $inscripcion->periodo,
            'concepto' => $concepto,
            'pagoOk' => $concepto !== null && $concepto->estatus === EstatusConceptoPago::PAGADO,
            'convenioEstado' => $inscripcion->convenio_id === null
                ? null
                : $inscripcion->convenio_estatus?->value,
            'convenioOk' => $inscripcion->convenio_id === null
                || $inscripcion->convenio_estatus === EstatusConvenio::APROBADO,
            'documentos' => $documentos,
            'documentosOk' => app(DocumentoService::class)->obligatoriosTodosAceptados($inscripcion),
            'faltantesAbiertos' => $faltantesAbiertos,
            'requisitos' => $requisitos,
            'enRevisionFinal' => $enRevisionFinal,
            'puedeFinalizar' => Gate::allows('inscripcion.finalizar')
                && $enRevisionFinal
                && $requisitos === [],
            // Nunca se pasa información médica a la vista (RB-18).
        ]);
    }

    private function inscripcionOrFail(): Inscripcion
    {
        return Inscripcion::findOrFail($this->inscripcionId);
    }
}
