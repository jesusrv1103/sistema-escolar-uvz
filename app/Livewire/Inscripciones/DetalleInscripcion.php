<?php

namespace App\Livewire\Inscripciones;

use App\Models\Inscripcion;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Detalle operativo de la inscripción (Fase 14): SOLO LECTURA.
 *
 * Integra en la vista existente (`inscripciones.ver`, el wizard) las cinco
 * secciones que pide la arquitectura para el detalle:
 *  - Estado general: alumno, número de control, programa, periodo, estatus
 *    de `InscripcionEstatus` y los hitos ya almacenados (incluida la
 *    cancelación: fecha, responsable y motivo).
 *  - Convenio: no aplica / PENDIENTE_APROBACION / APROBADO / RECHAZADO con
 *    el snapshot de descuento y quién lo aplicó/aprobó. SIN acciones: las
 *    viven en `ModalAplicarConvenio` (no se duplica funcionalidad).
 *  - Pagos: concepto, monto, estatus, folio, fecha y método/referencia de
 *    los intentos de los conceptos; comprobante/PDF solo mediante las rutas
 *    existentes con policy (`pagos.comprobante`, `pagos.comprobante-pdf`);
 *    nunca se muestra la ruta privada. No modifica `PagoService`.
 *  - Documentos: tipo, obligatorio/opcional, estatus, fecha y validación;
 *    las acciones siguen en `PanelDocumentos`. Sin paths ni UUIDs.
 *  - Historial de auditoría: EXCLUSIVO de la capacidad `auditoria.view`
 *    (matriz §8.2 = solo ADMIN); append-only, orden del proyecto
 *    (más reciente primero), sin payload `datos` ni datos técnicos crudos.
 *
 * Autorización server-side: `view` de la inscripción en `mount()` — un
 * usuario inactivo o sin acceso recibe 403 aunque se fuerce el acceso.
 * Sin información médica (RB-18) y sin nuevas rutas.
 */
class DetalleInscripcion extends Component
{
    public int $inscripcionId;

    public function mount(Inscripcion $inscripcion): void
    {
        Gate::authorize('view', $inscripcion);
        $this->inscripcionId = $inscripcion->getKey();
    }

    public function render()
    {
        $inscripcion = Inscripcion::query()
            ->with([
                'alumno',
                'programa',
                'periodo',
                'convenio',
                'convenioAplicadoPor',
                'convenioAprobadoPor',
                'validadaPor',
                'canceladaPor',
            ])
            ->findOrFail($this->inscripcionId);

        // Pagos agrupados por concepto (INSCRIPCION primero, luego mensualidades),
        // con los intentos en orden cronológico de registro.
        $conceptos = $inscripcion->conceptosPago()
            ->with([
                'pagos' => fn ($query) => $query->orderBy('id'),
                'pagos.registradoPor',
                'pagos.validadoPor',
            ])
            ->orderBy('tipo')
            ->orderBy('numero')
            ->get();

        $documentos = $inscripcion->documentos()
            ->with(['tipoDocumento', 'validadoPor'])
            ->orderBy('id')
            ->get();

        // Historial solo para quien tiene la capacidad auditoria.view (§8.2:
        // ADMIN). Para el resto ni siquiera se consulta.
        $puedeVerHistorial = Gate::allows('auditoria.view');
        $historial = $puedeVerHistorial
            ? $inscripcion->auditorias()
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();

        return view('livewire.inscripciones.detalle-inscripcion', [
            'inscripcion' => $inscripcion,
            'conceptos' => $conceptos,
            'documentos' => $documentos,
            'historial' => $historial,
            'puedeVerHistorial' => $puedeVerHistorial,
            // Nunca se consulta ni se pasa información médica a la vista (RB-18).
        ]);
    }
}
