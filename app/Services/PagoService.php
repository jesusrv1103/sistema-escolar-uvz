<?php

namespace App\Services;

use App\Enums\EstatusConceptoPago;
use App\Enums\EstatusPago;
use App\Enums\InscripcionEstatus;
use App\Enums\MetodoPago;
use App\Enums\TipoConcepto;
use App\Exceptions\PagoInvalidoException;
use App\Models\ConceptoPago;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Único punto de negocio de pagos (Fase 8, RB-4/RB-5/RB-6, FC-5).
 *
 * Tres operaciones, cada una en UNA transacción con ROLLBACK TOTAL:
 *  - `registrar()` (ADMISIONES/ADMINISTRACION): intento `PENDIENTE`, comprobante
 *    a disco privado con compensación de archivos huérfanos; mueve la
 *    inscripción solo si el concepto es `INSCRIPCION` (vía
 *    `InscripcionService::transicionar`, jamás asignando estatus directamente).
 *  - `validar()` (ADMINISTRACION, four-eyes): folio único inmutable, concepto
 *    `PAGADO` y, solo para `INSCRIPCION`, `PAYMENT_REVIEW → PAYMENT_VALIDATED
 *    → PENDING_DOCUMENTS`.
 *  - `rechazar()` (ADMINISTRACION): motivo obligatorio, sin folio; el intento
 *    RECHAZADO se conserva como historial y permite un nuevo intento después.
 *
 * La UI NUNCA modifica estatus, folio, validado_*, motivo ni el estado del
 * concepto/la inscripción: todo pasa por aquí, con `lockForUpdate()` para
 * impedir dos intentos activos concurrentes sobre el mismo concepto.
 */
class PagoService
{
    /** Tamaño máximo del comprobante cargado: 10 MB (§8.2/FC-5). */
    private const MAX_COMPROBANTE_BYTES = 10485760;

    /** MIME real admitido → extensión con la que se ALMACENA (nunca la original). */
    private const MIME_COMPROBANTE = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** Extensión del nombre original admitida (además del MIME real). */
    private const EXTENSIONES_COMPROBANTE = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Disco privado: fuera de `public/` (riesgo R5). */
    private const DISCO_COMPROBANTE = 'private';

    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly InscripcionService $inscripciones,
        private readonly FolioPagoService $folio,
    ) {
    }

    /**
     * Registra un intento de pago sobre un concepto `PENDIENTE` (FC-5).
     *
     * Concurrencia: el concepto se bloquea con `lockForUpdate()`; dentro del
     * lock solo se permite registrar si NO hay pago `VALIDADO` ni otro
     * `PENDIENTE` (un único intento activo). Los `RECHAZADO` históricos no
     * bloquean: se conservan y se permite el nuevo intento.
     *
     * El monto debe ser EXACTAMENTE `concepto.monto_total` (> 0): no hay
     * pagos parciales ni anticipos (D5). El comprobante es opcional; si se
     * adjunta, se valida (extensión + MIME real + ≤ 10 MB) y se guarda en el
     * disco privado con ruta generada por el sistema. Si la transacción de BD
     * falla DESPUÉS de escribir el archivo, éste se elimina (compensación):
     * nunca quedan comprobantes huérfanos.
     *
     * Efecto de proceso SOLO para concepto `INSCRIPCION`:
     * `PENDING_PAYMENT → PAYMENT_REVIEW` vía `InscripcionService` (la
     * `MENSUALIDAD` jamás modifica `inscripciones.estatus`).
     *
     * @param array<string, mixed> $datos monto, fecha_pago, metodo, referencia,
     *                                    notas y comprobante (UploadedFile|null)
     */
    public function registrar(ConceptoPago $concepto, array $datos, ?User $usuario = null): Pago
    {
        Gate::authorize('pago.registrar');
        $usuario ??= auth()->user();

        $comprobante = $datos['comprobante'] ?? null;

        // Normalización server-side: textos recortados y vacíos en null.
        $referencia = isset($datos['referencia']) && is_string($datos['referencia']) ? trim($datos['referencia']) : '';
        $notas = isset($datos['notas']) && is_string($datos['notas']) ? trim($datos['notas']) : '';
        $datos['referencia'] = $referencia === '' ? null : $referencia;
        $datos['notas'] = $notas === '' ? null : $notas;

        if ($comprobante !== null) {
            // Falla ANTES de tocar la BD: sin archivos que compensar.
            $this->validarComprobante($comprobante);
        }

        $rutaAlmacenada = null;

        try {
            return DB::transaction(function () use ($concepto, $datos, $usuario, $comprobante, &$rutaAlmacenada): Pago {
                // Fila del concepto con lock: la base del control de concurrencia.
                $concepto = ConceptoPago::query()->lockForUpdate()->findOrFail($concepto->getKey());

                if ($concepto->estatus !== EstatusConceptoPago::PENDIENTE) {
                    // Cubre el caso inscripción $0: su concepto nace PAGADO (Fase 7).
                    throw new PagoInvalidoException(sprintf(
                        'El concepto "%s" ya no está pendiente (estatus actual: %s): no admite pagos.',
                        $concepto->concepto,
                        $concepto->estatus->value,
                    ));
                }

                // Historial del concepto bloqueado: nunca dos intentos activos.
                $previos = $concepto->pagos()->lockForUpdate()->get();

                if ($previos->contains(fn (Pago $pago): bool => $pago->estatus === EstatusPago::VALIDADO)) {
                    throw new PagoInvalidoException('El concepto ya fue liquidado por un pago validado.');
                }

                if ($previos->contains(fn (Pago $pago): bool => $pago->estatus === EstatusPago::PENDIENTE)) {
                    throw new PagoInvalidoException('Ya existe un intento de pago pendiente para este concepto: valida o rechaza ese intento antes de registrar otro.');
                }

                // Solo los RECHAZADO históricos pueden existir aquí (se conservan).
                $inscripcion = $concepto->inscripcion()->firstOrFail();
                $this->validarDatos($concepto, $datos);

                if ($concepto->tipo === TipoConcepto::INSCRIPCION) {
                    if ($inscripcion->estatus !== InscripcionEstatus::PENDING_PAYMENT) {
                        throw new PagoInvalidoException(sprintf(
                            'El concepto de inscripción solo admite pagos en PENDING_PAYMENT (estatus actual: %s).',
                            $inscripcion->estatus->value,
                        ));
                    }
                } elseif ($inscripcion->estatus === InscripcionEstatus::CANCELLED) {
                    throw new PagoInvalidoException('No se pueden registrar pagos sobre una inscripción cancelada.');
                }

                $ruta = null;
                $nombreOriginal = null;

                if ($comprobante !== null) {
                    // Se escribe al final: la mayor parte de los fallos ocurre antes.
                    [$ruta, $nombreOriginal] = $this->almacenarComprobante($comprobante);
                    $rutaAlmacenada = $ruta;
                }

                $pago = new Pago();
                $pago->forceFill([
                    'concepto_pago_id' => $concepto->getKey(),
                    'monto' => number_format((float) $datos['monto'], 2, '.', ''),
                    'fecha_pago' => $datos['fecha_pago'],
                    'metodo' => $datos['metodo'],
                    'referencia' => $datos['referencia'] ?? null,
                    'notas' => $datos['notas'] ?? null,
                    'comprobante_ruta' => $ruta,
                    'comprobante_nombre_original' => $nombreOriginal,
                    'estatus' => EstatusPago::PENDIENTE->value,   // default de columna, explícito por claridad
                    'registrado_por' => $usuario?->getKey(),
                ])->save();
                $pago->refresh();

                // Efecto de proceso SOLO para INSCRIPCION (FC-5): jamás asignando
                // estatus directamente; todo vía máquina de estados.
                if ($concepto->tipo === TipoConcepto::INSCRIPCION) {
                    $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::PAYMENT_REVIEW, null, $usuario);
                }

                $this->auditoria->registrar(
                    $pago,
                    'pago_registrado',
                    [],
                    ['estatus' => EstatusPago::PENDIENTE->value],
                    [
                        'concepto_pago_id' => $concepto->getKey(),
                        'tipo' => $concepto->tipo->value,
                        'monto' => $pago->monto,
                        'metodo' => $pago->metodo->value,
                    ],
                    $usuario,
                );

                return $pago;
            });
        } catch (Throwable $e) {
            // Compensación: si el archivo se escribió y la transacción falló
            // después, se elimina para no dejar comprobantes huérfanos.
            if ($rutaAlmacenada !== null) {
                Storage::disk(self::DISCO_COMPROBANTE)->delete($rutaAlmacenada);
            }

            throw $e;
        }
    }

    /**
     * Valida un pago `PENDIENTE` (four-eyes RB-5/RB-6, solo ADMINISTRACION
     * vía gate `pago.validar`; ADMIN pasa por `Gate::before`).
     *
     * En UNA transacción y en orden: lock del pago → lock del concepto →
     * montos → folio `PAG-YYYYMMDD-########` (fecha de VALIDACIÓN con el
     * timezone de la app) → pago `VALIDADO` con responsables → concepto
     * `PAGADO` + `pagado_at` → auditoría `pago_validado` → efecto de proceso
     * SOLO si es `INSCRIPCION` (`PAYMENT_REVIEW → PAYMENT_VALIDATED →
     * PENDING_DOCUMENTS`, ambas vía `InscripcionService`, llenando
     * `pagos_validados_at`). Cualquier fallo ⇒ ROLLBACK TOTAL: sin folio
     * huérfano, sin concepto PAGADO con pago PENDIENTE, sin inscripción
     * avanzada a medias.
     */
    public function validar(Pago $pago, ?User $usuario = null): Pago
    {
        Gate::authorize('pago.validar');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($pago, $usuario): Pago {
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->getKey());

            if ($pago->estatus !== EstatusPago::PENDIENTE) {
                throw new PagoInvalidoException(sprintf(
                    'Solo se validan pagos PENDIENTE (estatus actual: %s).',
                    $pago->estatus->value,
                ));
            }

            $concepto = ConceptoPago::query()->lockForUpdate()->findOrFail($pago->concepto_pago_id);

            if ($concepto->estatus !== EstatusConceptoPago::PENDIENTE) {
                throw new PagoInvalidoException('El concepto ya no está pendiente: no se puede validar este pago.');
            }

            if (round((float) $pago->monto, 2) !== round((float) $concepto->monto_total, 2)) {
                throw new PagoInvalidoException('El monto del pago no coincide con el monto total del concepto.');
            }

            // 1. Folio único con la fecha/hora de validación (inmutable, RB-10).
            $folio = $this->folio->generar($pago);

            $pago->forceFill([
                'estatus' => EstatusPago::VALIDADO->value,
                'folio' => $folio,
                'validado_por' => $usuario?->getKey(),
                'validado_at' => now(),
            ])->save();

            // 2. El concepto queda liquidado (un pago por concepto ⇒ saldo 0).
            $concepto->forceFill([
                'estatus' => EstatusConceptoPago::PAGADO->value,
                'pagado_at' => now(),
            ])->save();

            // 3. Auditoría en la MISMA transacción (RB-12).
            $this->auditoria->registrar(
                $pago,
                'pago_validado',
                ['estatus' => EstatusPago::PENDIENTE->value],
                ['estatus' => EstatusPago::VALIDADO->value],
                ['folio' => $folio, 'concepto_pago_id' => $concepto->getKey()],
                $usuario,
            );

            // 4. Efecto de proceso SOLO para INSCRIPCION (FC-5): dos
            //    transiciones encadenadas vía la máquina de estados.
            if ($concepto->tipo === TipoConcepto::INSCRIPCION) {
                $inscripcion = $concepto->inscripcion()->lockForUpdate()->findOrFail($concepto->inscripcion_id);

                if ($inscripcion->estatus !== InscripcionEstatus::PAYMENT_REVIEW) {
                    throw new PagoInvalidoException(sprintf(
                        'La inscripción debe estar en PAYMENT_REVIEW para validar el pago de inscripción (estatus actual: %s).',
                        $inscripcion->estatus->value,
                    ));
                }

                $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::PAYMENT_VALIDATED, null, $usuario);
                $this->inscripciones->transicionar($inscripcion->refresh(), InscripcionEstatus::PENDING_DOCUMENTS, null, $usuario);
            }

            return $pago->refresh();
        });
    }

    /**
     * Rechaza un pago `PENDIENTE` con motivo obligatorio (≤ 2000, sin ser
     * solo espacios). No genera folio, no borra el registro ni su
     * comprobante (historial intacto) y deja el concepto `PENDIENTE` para un
     * nuevo intento.
     *
     * Efecto de proceso SOLO para `INSCRIPCION`:
     * `PAYMENT_REVIEW → PENDING_PAYMENT` (retroceso) usando el motivo del
     * rechazo como motivo explícito de la transición. Una `MENSUALIDAD`
     * rechazada NUNCA modifica `inscripciones.estatus`.
     */
    public function rechazar(Pago $pago, string $motivo, ?User $usuario = null): Pago
    {
        Gate::authorize('pago.validar');
        $usuario ??= auth()->user();

        $motivo = trim($motivo);

        if ($motivo === '' || mb_strlen($motivo) > 2000) {
            throw new PagoInvalidoException('El motivo de rechazo es obligatorio y no puede exceder 2000 caracteres.');
        }

        return DB::transaction(function () use ($pago, $motivo, $usuario): Pago {
            $pago = Pago::query()->lockForUpdate()->findOrFail($pago->getKey());

            if ($pago->estatus !== EstatusPago::PENDIENTE) {
                throw new PagoInvalidoException(sprintf(
                    'Solo se rechazan pagos PENDIENTE (estatus actual: %s).',
                    $pago->estatus->value,
                ));
            }

            $concepto = ConceptoPago::query()->lockForUpdate()->findOrFail($pago->concepto_pago_id);

            if ($concepto->estatus !== EstatusConceptoPago::PENDIENTE) {
                throw new PagoInvalidoException('El concepto ya no está pendiente: no se puede rechazar este pago.');
            }

            $pago->forceFill([
                'estatus' => EstatusPago::RECHAZADO->value,
                'motivo_rechazo' => $motivo,
                'folio' => null,            // un RECHAZADO jamás recibe folio (RB-10)
                'validado_por' => null,
                'validado_at' => null,
            ])->save();

            $this->auditoria->registrar(
                $pago,
                'pago_rechazado',
                ['estatus' => EstatusPago::PENDIENTE->value],
                ['estatus' => EstatusPago::RECHAZADO->value],
                ['motivo' => $motivo, 'concepto_pago_id' => $concepto->getKey()],
                $usuario,
            );

            if ($concepto->tipo === TipoConcepto::INSCRIPCION) {
                // Retroceso con motivo explícito (§7.3.2): el concepto queda
                // PENDIENTE y ADMISIONES podrá registrar un nuevo intento.
                $inscripcion = $concepto->inscripcion()->lockForUpdate()->findOrFail($concepto->inscripcion_id);
                $this->inscripciones->transicionar($inscripcion, InscripcionEstatus::PENDING_PAYMENT, $motivo, $usuario);
            }

            return $pago->refresh();
        });
    }

    /**
     * Datos del intento: monto exacto del concepto (> 0), fecha no futura,
     * método válido y referencia obligatoria para TRANSFERENCIA/TARJETA.
     *
     * @param array<string, mixed> $datos
     */
    private function validarDatos(ConceptoPago $concepto, array $datos): void
    {
        $montoTotal = round((float) $concepto->monto_total, 2);
        $monto = isset($datos['monto']) && is_numeric($datos['monto']) ? round((float) $datos['monto'], 2) : null;

        if ($monto === null || $monto <= 0) {
            throw new PagoInvalidoException('El monto del pago debe ser mayor a 0.');
        }

        if ($monto !== $montoTotal) {
            throw new PagoInvalidoException(sprintf(
                'El monto debe ser exactamente el total del concepto (%s): no se admiten pagos parciales.',
                number_format($montoTotal, 2),
            ));
        }

        $fecha = $datos['fecha_pago'] ?? null;

        if (! is_string($fecha) || trim($fecha) === '') {
            throw new PagoInvalidoException('La fecha de pago es obligatoria.');
        }

        try {
            $fechaPago = Carbon::parse($fecha);
        } catch (Throwable) {
            throw new PagoInvalidoException('La fecha de pago no es válida.');
        }

        if ($fechaPago->startOfDay()->gt(today())) {
            throw new PagoInvalidoException('La fecha de pago no puede ser futura.');
        }

        $metodo = MetodoPago::tryFrom((string) ($datos['metodo'] ?? ''));

        if ($metodo === null) {
            throw new PagoInvalidoException('El método de pago no es válido.');
        }

        $referencia = is_string($datos['referencia'] ?? null) ? trim($datos['referencia']) : '';

        if (in_array($metodo, [MetodoPago::TRANSFERENCIA, MetodoPago::TARJETA], true) && $referencia === '') {
            throw new PagoInvalidoException(sprintf(
                'La referencia de operación es obligatoria para el método %s.',
                $metodo->value,
            ));
        }

        if (mb_strlen($referencia) > 100) {
            throw new PagoInvalidoException('La referencia no puede exceder 100 caracteres.');
        }
    }

    /**
     * Valida el comprobante CARGADO: extensión admitida + MIME real con
     * `finfo` (nunca solo la extensión) + ≤ 10 MB. Lanza antes de escribir
     * en BD.
     */
    private function validarComprobante(UploadedFile $archivo): void
    {
        if (! $archivo->isValid()) {
            throw new PagoInvalidoException('El comprobante no se pudo recibir correctamente.');
        }

        if ($archivo->getSize() > self::MAX_COMPROBANTE_BYTES) {
            throw new PagoInvalidoException('El comprobante excede el tamaño máximo de 10 MB.');
        }

        $extension = strtolower($archivo->getClientOriginalExtension());

        if (! in_array($extension, self::EXTENSIONES_COMPROBANTE, true)) {
            throw new PagoInvalidoException('Formato de comprobante no admitido: usa PDF, JPG, JPEG o PNG.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $archivo->getRealPath()) : false;

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($mime) || ! isset(self::MIME_COMPROBANTE[$mime])) {
            throw new PagoInvalidoException('El contenido del comprobante no corresponde a un PDF, JPG o PNG válido.');
        }
    }

    /**
     * Guarda el comprobante en el disco PRIVADO con una ruta generada por el
     * sistema (UUID + extensión derivada del MIME real). El nombre original
     * se conserva SOLO como metadato, jamás como ruta.
     *
     * @return array{0: string, 1: string} [ruta interna, nombre original]
     */
    private function almacenarComprobante(UploadedFile $archivo): array
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $archivo->getRealPath()) : false;

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $extension = is_string($mime) ? (self::MIME_COMPROBANTE[$mime] ?? null) : null;

        if ($extension === null) {
            throw new PagoInvalidoException('El contenido del comprobante no corresponde a un PDF, JPG o PNG válido.');
        }

        $ruta = $archivo->storeAs(
            'pagos',
            Str::uuid()->toString().'.'.$extension,
            self::DISCO_COMPROBANTE,
        );

        if ($ruta === false) {
            throw new PagoInvalidoException('No fue posible almacenar el comprobante.');
        }

        return [$ruta, Str::limit(basename($archivo->getClientOriginalName()), 255, '')];
    }
}
