<?php

namespace App\Services;

use App\Enums\EstatusPago;
use App\Exceptions\PagoInvalidoException;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;

/**
 * Comprobante institucional PDF de un pago (Fase 9, RB-19).
 *
 * - Se genera BAJO DEMANDA: el PDF nunca se persiste en BD ni se guarda una
 *   copia en disco. No es factura ni CFDI (sin QR, sin sello, sin datos
 *   fiscales inventados).
 * - Únicamente pagos `VALIDADO` con folio, validación e integridad mínima
 *   (concepto → inscripción → alumno/programa) completas; un intento
 *   `PENDIENTE` o `RECHAZADO` jamás genera comprobante.
 * - Operación de LECTURA: no modifica Pago, ConceptoPago, Inscripcion ni
 *   escribe auditoría (la arquitectura no audita descargas).
 * - Toda la identidad institucional sale de `config('branding.*')`; el logo
 *   se resuelve como data URI local (sin URLs públicas ni internet).
 *
 * Preparado para crecer, pero implementar únicamente lo que el MVP necesita.
 */
class ComprobantePagoService
{
    /**
     * Relaciones mínimas necesarias para renderizar el documento.
     *
     * @var list<string>
     */
    private const RELACIONES = [
        'conceptoPago.inscripcion.alumno',
        'conceptoPago.inscripcion.programa',
        'validadoPor',
    ];

    /**
     * Prepara los datos con los que se renderiza el comprobante.
     *
     * @return array<string, mixed>
     *
     * @throws PagoInvalidoException si el pago no está VALIDADO o le falta
     *                              la integridad mínima exigida.
     */
    public function datos(Pago $pago): array
    {
        if ($pago->estatus !== EstatusPago::VALIDADO) {
            throw new PagoInvalidoException(sprintf(
                'El comprobante solo se genera para un pago VALIDADO (estatus actual: %s).',
                $pago->estatus?->value ?? 'desconocido',
            ));
        }

        $pago->load(self::RELACIONES);
        $this->verificarIntegridad($pago);

        $inscripcion = $pago->conceptoPago->inscripcion;
        $alumno = $inscripcion->alumno;

        return [
            // Branding (fuente única: config/branding.php)
            'logo' => $this->logoDataUri(),
            'institucion' => (string) config('branding.name'),
            'sistema' => (string) config('branding.system_name'),
            'slogan' => (string) config('branding.slogan'),
            'colores' => [
                'primario' => (string) config('branding.primary_color'),
                'secundario' => (string) config('branding.secondary_color'),
                'acento' => (string) config('branding.accent_color'),
                'texto' => (string) config('branding.text_color'),
                'tenue' => (string) config('branding.muted_color'),
                'borde' => (string) config('branding.border_color'),
                'fondo' => (string) config('branding.surface_color'),
            ],
            'contacto' => [
                'telefono' => (string) config('branding.phone'),
                'correo' => (string) config('branding.email'),
                'domicilio' => (string) config('branding.address'),
            ],
            // Contenido del comprobante (§4 / RB-19)
            'folio' => (string) $pago->folio,
            'fechaPago' => $pago->fecha_pago?->format('d/m/Y'),
            'fechaValidacion' => $pago->validado_at->format('d/m/Y H:i'),
            'alumno' => trim($alumno->nombre.' '.$alumno->apellidos),
            'numeroControl' => $alumno->numero_control,
            'programa' => $inscripcion->programa->nombre,
            'concepto' => $pago->conceptoPago->concepto,
            // Presentación en español (etiquetas); el valor técnico vive en BD.
            'tipoConcepto' => $pago->conceptoPago->tipo->label(),
            'monto' => '$'.number_format((float) $pago->monto, 2),
            'moneda' => 'MXN',
            'metodo' => $pago->metodo?->label(),
            'referencia' => $pago->referencia,
        ];
    }

    /**
     * Genera el PDF bajo demanda (en memoria, sin persistir copia alguna).
     *
     * @throws PagoInvalidoException si el pago no es VALIDADO o es inconsistente.
     */
    public function generar(Pago $pago): DocumentoPdf
    {
        return Pdf::loadView('pdf.comprobante-pago', $this->datos($pago));
    }

    /**
     * Guardas de integridad mínima: folio/validación presentes y relaciones
     * de negocio resueltas. Nada de esto se "arregla" aquí: se rechaza.
     */
    private function verificarIntegridad(Pago $pago): void
    {
        if ($pago->folio === null || $pago->validado_at === null || $pago->validadoPor === null) {
            throw new PagoInvalidoException('El pago validado no tiene folio o validación completa: comprobante no disponible.');
        }

        if ($pago->conceptoPago === null || $pago->conceptoPago->inscripcion === null) {
            throw new PagoInvalidoException('El pago no tiene concepto o inscripción asociada: comprobante no disponible.');
        }

        $inscripcion = $pago->conceptoPago->inscripcion;

        if ($inscripcion->alumno === null || $inscripcion->programa === null) {
            throw new PagoInvalidoException('La inscripción no tiene alumno o programa: comprobante no disponible.');
        }

        if ((float) $pago->monto <= 0) {
            throw new PagoInvalidoException('El monto del pago debe ser mayor a 0: comprobante no disponible.');
        }
    }

    /**
     * Logo institucional como data URI: resuelve `config('branding.logo')`
     * dentro de `public/` de forma segura (sin exponer rutas del servidor en
     * el PDF y sin descargar nada de internet).
     */
    private function logoDataUri(): ?string
    {
        $relativo = (string) config('branding.logo');

        if ($relativo === '') {
            return null;
        }

        $publica = realpath(public_path());
        $archivo = realpath(public_path($relativo));

        if ($publica === false
            || $archivo === false
            || ! is_file($archivo)
            || ! str_starts_with($archivo, $publica.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archivo) ?: '';

        if (! str_starts_with($mime, 'image/')) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mime, base64_encode((string) file_get_contents($archivo)));
    }
}
