<?php

namespace App\Services;

use App\Enums\EstatusConvenio;
use App\Enums\EstatusConceptoPago;
use App\Enums\InscripcionEstatus;
use App\Enums\TipoConcepto;
use App\Exceptions\CargosInvalidosException;
use App\Exceptions\CancelacionInvalidaException;
use App\Exceptions\ConvenioInvalidoException;
use App\Exceptions\FinalizacionInvalidaException;
use App\Exceptions\MotivoRequeridoException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Único punto autorizado para modificar `inscripciones.estatus` (§7.3.6).
 *
 * Toda transición:
 *  1. se valida contra la matriz única `InscripcionEstatus::TRANSICIONES` (§7.3.1);
 *  2. exige motivo cuando corresponde (retrocesos y cancelaciones, §7.3.2);
 *  3. ocurre dentro de una transacción de BD (§7.3.3);
 *  4. registra auditoría y usuario responsable en la misma transacción.
 *
 * Las guardas de negocio viven en cada módulo y en las fases que las
 * incorporan (§7.3.5): la revisión final RB-8 y la asignación del número de
 * control están en `finalizar()` (Fase 12).
 */
class InscripcionService
{
    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly NumeroControlService $numeroControl,
    ) {
    }

    public function puedeTransicionar(InscripcionEstatus $desde, InscripcionEstatus $hasta): bool
    {
        return $desde->puedeIrA($hasta);
    }

    /**
     * Crea el borrador de inscripción en `DRAFT` (FC-2, §4.8).
     *
     * - `estatus` NO se envía: lo da el default de columna (`DRAFT`).
     * - Los montos son *snapshots* capturados aquí (no existe catálogo de tarifas).
     * - Los datos académicos (alumno/periodo/programa) ya fueron validados por
     *   la capa de presentación (§12.4 + RB-13/RB-14); `UNIQUE(alumno_id,
     *   periodo_id)` es la última línea de defensa.
     *
     * @param  array<string, mixed>  $datos
     */
    public function crear(array $datos, ?User $usuario = null): Inscripcion
    {
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($datos, $usuario): Inscripcion {
            $inscripcion = Inscripcion::create($datos + ['creado_por' => $usuario?->getKey()]);
            $inscripcion->refresh();   // trae los defaults de columna (`estatus` = DRAFT)

            $this->auditoria->registrar(
                $inscripcion,
                'inscripcion_creada',
                [],
                [
                    'alumno_id' => $inscripcion->alumno_id,
                    'periodo_id' => $inscripcion->periodo_id,
                    'programa_id' => $inscripcion->programa_id,
                    'estatus' => $inscripcion->estatus->value,
                ],
                [],
                $usuario,
            );

            return $inscripcion;
        });
    }

    /**
     * Ejecuta la transición o lanza excepción explícita. Nunca deja la
     * inscripción en el nuevo estado si falla la auditoría (transacción).
     */
    public function transicionar(
        Inscripcion $inscripcion,
        InscripcionEstatus $hasta,
        ?string $motivo = null,
        ?User $usuario = null,
    ): Inscripcion {
        $usuario ??= auth()->user();
        $desde = $inscripcion->estatus;

        if (! $this->puedeTransicionar($desde, $hasta)) {
            throw new TransicionInvalidaException(
                sprintf('Transición inválida: %s → %s.', $desde->value, $hasta->value),
            );
        }

        $motivo = $motivo !== null && trim($motivo) !== '' ? trim($motivo) : null;

        if ($desde->requiereMotivo($hasta) && $motivo === null) {
            throw new MotivoRequeridoException(
                sprintf('La transición %s → %s requiere motivo explícito.', $desde->value, $hasta->value),
            );
        }

        return DB::transaction(function () use ($inscripcion, $desde, $hasta, $motivo, $usuario): Inscripcion {
            $inscripcion->forceFill(array_merge(
                ['estatus' => $hasta->value],
                $this->complementos($inscripcion, $hasta, $motivo, $usuario),
            ))->save();

            $this->auditoria->registrar(
                $inscripcion,
                'estado_cambiado',
                ['estatus' => $desde->value],
                ['estatus' => $hasta->value],
                $motivo === null ? [] : ['motivo' => $motivo],
                $usuario,
            );

            return $inscripcion;
        });
    }

    /**
     * Genera los cargos y avanza `DATA_COMPLETE → PENDING_PAYMENT` (RB-3,
     * FC-4) en **UNA sola transacción**:
     *
     *  1. valida la inscripción (solo desde `DATA_COMPLETE`, fila con lock);
     *  2. valida el convenio (si existe: `APROBADO` o `RECHAZADO`;
     *     `PENDIENTE_APROBACION` ⇒ bloqueo, F23);
     *  3. verifica que no existan cargos previos (idempotencia: la segunda
     *     ejecución se rechaza explícitamente, sin crear ni cambiar nada);
     *  4. crea 1 concepto `INSCRIPCION` (nace `PAGADO` si total = 0);
     *  5. crea exactamente 4 `MENSUALIDAD` (cuatrimestre UVZ, vencimiento el
     *     día `config('escolar.dia_vencimiento_mensualidad')` de los cuatro
     *     meses derivados de `periodos.fecha_inicio`, descuento 0);
     *  6. transiciona a `PENDING_PAYMENT` (auditoría `estado_cambiado`);
     *  7. registra la auditoría `cargos_generados`.
     *
     * Si cualquier paso falla: ROLLBACK TOTAL (nada de medias listas de
     * mensualidades ni inscripciones avanzadas sin cargos).
     *
     * El descuento de convenio (congelado en `monto_descuento`) se aplica
     * SOLO al concepto `INSCRIPCION` y solo si `convenio_estatus = APROBADO`;
     * las mensualidades nunca reciben descuento (decisión UVZ).
     *
     * Regla mínima documentada para el vencimiento del concepto
     * `INSCRIPCION`: la arquitectura no fija una fecha institucional, por lo
     * que se usa `periodos.fecha_inicio` (coherente con el periodo, sin
     * inventar una regla de negocio nueva).
     */
    public function generarCargos(Inscripcion $inscripcion, ?User $usuario = null): Inscripcion
    {
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($inscripcion, $usuario): Inscripcion {
            // Fila con lock: evita carreras de doble generación concurrente.
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcion->getKey());

            if ($inscripcion->estatus !== InscripcionEstatus::DATA_COMPLETE) {
                throw new CargosInvalidosException('Los cargos solo se generan desde DATA_COMPLETE.');
            }

            // 2. Convenio resuelto antes de avanzar a PENDING_PAYMENT (F23).
            if ($inscripcion->convenio_id !== null) {
                $estatusConvenio = $inscripcion->convenio_estatus;

                if ($estatusConvenio === EstatusConvenio::PENDIENTE_APROBACION) {
                    throw new ConvenioInvalidoException(
                        'El convenio está pendiente de aprobación: resuélvelo antes de generar los cargos (F23).',
                    );
                }

                if (! in_array($estatusConvenio, [EstatusConvenio::APROBADO, EstatusConvenio::RECHAZADO], true)) {
                    throw new ConvenioInvalidoException('El convenio de la inscripción no está resuelto: no se pueden generar cargos.');
                }
            }

            // 3. Idempotencia: nunca duplicar cargos.
            if ($inscripcion->conceptosPago()->exists()) {
                throw new CargosInvalidosException('La inscripción ya tiene cargos generados: no se crean duplicados.');
            }

            $periodo = $inscripcion->periodo()->firstOrFail();
            $montoInscripcion = round((float) $inscripcion->monto_inscripcion, 2);

            // 4. Concepto INSCRIPCION (descuento solo si convenio APROBADO).
            $descuento = $inscripcion->convenio_estatus === EstatusConvenio::APROBADO
                ? min(round((float) $inscripcion->monto_descuento, 2), $montoInscripcion)
                : 0.0;
            $montoTotal = max(round($montoInscripcion - $descuento, 2), 0.0);

            $inscripcion->conceptosPago()->create([
                'tipo' => TipoConcepto::INSCRIPCION->value,
                'numero' => null,
                'concepto' => 'Inscripción',
                'monto' => $montoInscripcion,
                'descuento' => $descuento,
                'monto_total' => $montoTotal,
                'fecha_vencimiento' => $periodo->fecha_inicio,
                'estatus' => ($montoTotal > 0 ? EstatusConceptoPago::PENDIENTE : EstatusConceptoPago::PAGADO)->value,
                'pagado_at' => $montoTotal > 0 ? null : now(),   // inscripción en $0 nace PAGADA
            ]);

            // 5. Mensualidades: 4 (cuatrimestre UVZ), sin descuento.
            $this->crearMensualidades($inscripcion, $periodo);

            // 6. Transición + auditoría `estado_cambiado` (misma transacción).
            $this->transicionar($inscripcion, InscripcionEstatus::PENDING_PAYMENT, null, $usuario);

            // 7. Auditoría de la generación (misma transacción, RB-12).
            $this->auditoria->registrar(
                $inscripcion,
                'cargos_generados',
                [],
                [],
                [
                    'conceptos' => $inscripcion->conceptosPago()->count(),
                    'monto_inscripcion_total' => number_format($montoTotal, 2, '.', ''),
                    'descuento' => number_format($descuento, 2, '.', ''),
                ],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Revisión final y asignación de número de control → `ENROLLED` (FC-7).
     *
     * UNA única transacción lógica (la más crítica del sistema, RB-12/R1) que
     * abarca, en orden:
     *  1. autorización server-side `inscripcion.finalizar` (ADMIN vía
     *     `Gate::before` o CONTROL_ESCOLAR; inactivos siempre 403, §8.2);
     *  2. `lockForUpdate()` de la inscripción y confirmación de `FINAL_REVIEW`
     *     (cualquier otro estatus ⇒ rechazo explícito; ENROLLED es terminal);
     *  3. guardas RB-8 completas vía `requisitosPendientes()` (forma
     *     reutilizable que también consume el PanelEstados);
     *  4. número de control dentro de ESTA MISMA transacción
     *     (`NumeroControlService` se anida como SAVEPOINT: sin commit propio);
     *  5. transición a `ENROLLED` por la máquina única ⇒ `finalizada_at`,
     *     `validada_por` y auditoría `estado_cambiado` (§7.3);
     *  6. auditoría `numero_control_asignado` (RB-12: acción crítica).
     *
     * Cualquier error ⇒ ROLLBACK TOTAL: jamás queda un NC asignado con la
     * inscripción en `FINAL_REVIEW`, un consecutivo huérfano, `ENROLLED` sin
     * auditoría ni hitos parciales.
     *
     * NO consulta mensualidades en ningún punto (RB-8 corregida: pueden
     * quedar `PENDIENTE` en una inscripción `ENROLLED`, F17).
     *
     * @throws FinalizacionInvalidaException si el estatus o una guarda RB-8 no se cumplen
     */
    public function finalizar(Inscripcion $inscripcion, ?User $usuario = null): Inscripcion
    {
        // 1. Autorización server-side: el @can de la vista solo complementa UX.
        Gate::authorize('inscripcion.finalizar');
        $usuario ??= auth()->user();

        return DB::transaction(function () use ($inscripcion, $usuario): Inscripcion {
            // 2. Fila con lock: evita finalizaciones concurrentes de la misma
            //    inscripción y lee el estado recién persistido.
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcion->getKey());

            if ($inscripcion->estatus !== InscripcionEstatus::FINAL_REVIEW) {
                throw new FinalizacionInvalidaException(sprintf(
                    'La finalización solo procede desde FINAL_REVIEW (estatus actual: %s).',
                    $inscripcion->estatus->value,
                ));
            }

            // 3. Guardas RB-8 (requisitos obligatorios únicamente, §11).
            $pendientes = $this->requisitosPendientes($inscripcion);
            if ($pendientes !== []) {
                throw new FinalizacionInvalidaException(
                    'Requisitos de la revisión final incompletos: '.implode(' ', $pendientes),
                );
            }

            // 4. Número de control en la MISMA transacción: conserva el
            //    existente o emite el siguiente de la secuencia del año del
            //    periodo (RB-9/P2).
            $alumno = $inscripcion->alumno()->firstOrFail();
            $conservado = $alumno->numero_control !== null;
            $numero = $this->numeroControl->asignar($alumno, $inscripcion);

            // 5. Transición por la máquina única: ENROLLED + finalizada_at +
            //    validada_por + auditoría `estado_cambiado` (todo en tx).
            $this->transicionar($inscripcion, InscripcionEstatus::ENROLLED, null, $usuario);

            // 6. RB-12: la asignación del número de control es acción crítica
            //    con su propio registro de trazabilidad (asignado o conservado).
            $this->auditoria->registrar(
                $inscripcion,
                'numero_control_asignado',
                [],
                [],
                [
                    'numero_control' => $numero,
                    'anio' => (int) substr($numero, 0, 4),
                    'conservado' => $conservado,
                ],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Cancela la inscripción → `CANCELLED` (FC-8/RB-11, Fase 13).
     *
     * Único punto de negocio de la cancelación. En UNA transacción:
     *  1. autorización server-side `inscripcion.cancelar` (solo ADMIN vía
     *     `Gate::before`; los demás roles y los inactivos 403, §8.2);
     *  2. motivo obligatorio con trim (no vacío ni solo espacios, ≤ 2000);
     *  3. `lockForUpdate()` de la inscripción y estado fresco;
     *  4. rechazo explícito de los estados terminales `ENROLLED` y
     *     `CANCELLED` (sin idempotencia silenciosa);
     *  5. conceptos: SOLO `PENDIENTE → CANCELADO` (los `PAGADO` y los ya
     *     `CANCELADO` quedan intactos; montos, descuentos y `pagado_at` no
     *     se tocan);
     *  6. transición por la máquina única (`transicionar()` escribe
     *     `cancelada_por`, `cancelada_at`, `cancelacion_motivo` y la
     *     auditoría `estado_cambiado` con el motivo);
     *  7. auditoría `inscripcion_cancelada` con contexto mínimo.
     *
     * Pagos, documentos, observaciones y número de control: INTACTOS (sin
     * deletes, sin cambios financieros, sin reembolsos — RB-11 los conserva
     * como historial).
     *
     * Si cualquier paso falla (incluida la auditoría): ROLLBACK TOTAL.
     *
     * @throws CancelacionInvalidaException si el motivo es inválido o el estatus no es cancelable
     * @throws AuthorizationException si el usuario no puede cancelar (403)
     */
    public function cancelar(Inscripcion $inscripcion, string $motivo, ?User $usuario = null): Inscripcion
    {
        // 1. Autorización server-side: el @can de la UI es solo UX.
        Gate::authorize('inscripcion.cancelar');
        $usuario ??= auth()->user();

        // 2. Motivo server-side: trim + obligatorio (mismo patrón que los
        //    rechazos de pagos/documentos/convenios: no vacío, ≤ 2000).
        $motivo = trim($motivo);
        $longitud = mb_strlen($motivo);

        if ($motivo === '' || $longitud > 2000) {
            throw new CancelacionInvalidaException('El motivo de la cancelación es obligatorio y no puede exceder 2000 caracteres.');
        }

        return DB::transaction(function () use ($inscripcion, $motivo, $usuario): Inscripcion {
            // 3. Fila con lock: estado recién persistido y cancelaciones
            //    concurrentes serializadas.
            $inscripcion = Inscripcion::query()->lockForUpdate()->findOrFail($inscripcion->getKey());

            // 4. Estados terminales: rechazo explícito (RB-11: ENROLLED no
            //    cancelable; CANCELLED no se vuelve a cancelar).
            if ($inscripcion->estatus->esTerminal()) {
                throw new CancelacionInvalidaException(sprintf(
                    'La inscripción está %s: es un estado terminal y no puede cancelarse.',
                    $inscripcion->estatus->value,
                ));
            }

            // 5. Conceptos: únicamente PENDIENTE → CANCELADO (update sobre la
            //    relación: no toca PAGADO/CANCELADO ni ningún monto).
            $conceptosCancelados = $inscripcion->conceptosPago()
                ->where('estatus', EstatusConceptoPago::PENDIENTE->value)
                ->update(['estatus' => EstatusConceptoPago::CANCELADO->value]);

            // 6. Transición por la máquina existente (valida el mapa de
            //    transiciones y exige motivo) con complementos + auditoría
            //    `estado_cambiado` anidados en ESTA transacción.
            $inscripcion = $this->transicionar(
                $inscripcion,
                InscripcionEstatus::CANCELLED,
                $motivo,
                $usuario,
            );

            // 7. Auditoría de negocio (RB-12): contexto mínimo sin duplicar
            //    el motivo (ya vive en `cancelacion_motivo` y en el payload
            //    de `estado_cambiado`).
            $this->auditoria->registrar(
                $inscripcion,
                'inscripcion_cancelada',
                [],
                [],
                ['conceptos_cancelados' => $conceptosCancelados],
                $usuario,
            );

            return $inscripcion->refresh();
        });
    }

    /**
     * Guardas RB-8 en forma reutilizable (§11): devuelve los requisitos
     * FALTANTES de la revisión final; `[]` = todo cumplido.
     *
     * La consumen `finalizar()` (bloqueo server-side) y `PanelEstados`
     * (indicar en UI qué requisito falta): una sola definición de las guardas.
     *
     * RB-8 explícito sobre lo que NO bloquea: este método NUNCA consulta
     * mensualidades ni pagos `MENSUALIDAD` (pueden quedar `PENDIENTE`), ni
     * observaciones que no sean `FALTANTE` + `ABIERTA`.
     *
     * El punto RB-8.4 (Control Escolar responsable) lo garantizan la
     * autorización `inscripcion.finalizar` + `validada_por` que la transición
     * a `ENROLLED` escribe server-side.
     *
     * @return list<string>
     */
    public function requisitosPendientes(Inscripcion $inscripcion): array
    {
        $faltantes = [];

        // RB-8.1: concepto `INSCRIPCION` con estatus `PAGADO` (no basta con
        // una mensualidad pagada; el cargo puede no existir si nunca se
        // generó).
        $concepto = $inscripcion->conceptoInscripcion;
        if ($concepto === null) {
            $faltantes[] = 'Falta el concepto de pago de INSCRIPCION.';
        } elseif ($concepto->estatus !== EstatusConceptoPago::PAGADO) {
            $faltantes[] = 'El concepto INSCRIPCION no está PAGADO.';
        }

        // RB-8.2: todos los tipos obligatorios aplicables en `ACEPTADO`
        // (reutiliza DocumentoService: obligatorio + activo + no eliminado;
        // los opcionales jamás bloquean).
        if (! app(DocumentoService::class)->obligatoriosTodosAceptados($inscripcion)) {
            $faltantes[] = 'Todos los documentos obligatorios deben estar ACEPTADOS.';
        }

        // RB-8.3: ninguna observación `FALTANTE` + `ABIERTA` (reutiliza
        // Inscripcion::tieneFaltantesAbiertos(), Fase 11).
        if ($inscripcion->tieneFaltantesAbiertos()) {
            $faltantes[] = 'Hay observaciones FALTANTE abiertas.';
        }

        // RB-8.5 (decisión aprobada Fase 12): sin convenio ⇒ OK; convenio
        // aplicado ⇒ debe estar RESUELTO (`APROBADO` o `RECHAZADO`). El
        // rechazo se considera resuelto SIN DESCUENTO: la Fase 7 ya permite
        // generar cargos con `RECHAZADO` (descuento 0, cargos normales), así
        // que bloquearlo aquí dejaría un estado imposible de resolver.
        // Único bloqueante: `PENDIENTE_APROBACION`.
        if ($inscripcion->convenio_id !== null
            && $inscripcion->convenio_estatus === EstatusConvenio::PENDIENTE_APROBACION) {
            $faltantes[] = 'El convenio está PENDIENTE_APROBACION.';
        }

        return $faltantes;
    }

    /**
     * Exactamente las 4 mensualidades del cuatrimestre UVZ: los cuatro meses
     * derivados de `periodos.fecha_inicio` (mes 1 = mes de inicio, +1, +2, +3),
     * con vencimiento el día institucional configurado en
     * `config('escolar.dia_vencimiento_mensualidad')`. Si el día no existe en
     * un mes, se usa el último día válido del mes.
     */
    private function crearMensualidades(Inscripcion $inscripcion, Periodo $periodo): void
    {
        $dia = max(1, (int) config('escolar.dia_vencimiento_mensualidad', 10));
        $total = max(1, (int) config('escolar.mensualidades_por_periodo', 4));
        $montoMensualidad = round((float) $inscripcion->monto_mensualidad, 2);
        $base = $periodo->fecha_inicio->copy()->startOfMonth();

        for ($indice = 0; $indice < $total; $indice++) {
            $mes = $base->copy()->addMonthsNoOverflow($indice);
            $fecha = $mes->setDay(min($dia, $mes->daysInMonth));
            $numero = $indice + 1;

            $inscripcion->conceptosPago()->create([
                'tipo' => TipoConcepto::MENSUALIDAD->value,
                'numero' => $numero,
                'concepto' => sprintf(
                    'Mensualidad %d - %s',
                    $numero,
                    $fecha->copy()->locale('es_MX')->isoFormat('MMMM YYYY'),
                ),
                'monto' => $montoMensualidad,
                'descuento' => '0.00',
                'monto_total' => $montoMensualidad,
                'fecha_vencimiento' => $fecha,
                'estatus' => EstatusConceptoPago::PENDIENTE->value,
                'pagado_at' => null,
            ]);
        }
    }

    /**
     * Hitos y responsables que la propia transición deja registrados.
     *
     * @return array<string, mixed>
     */
    private function complementos(
        Inscripcion $inscripcion,
        InscripcionEstatus $hasta,
        ?string $motivo,
        ?User $usuario,
    ): array {
        $campos = [];

        // Hitos: se marcan una sola vez (no se pisan en reintentos/retrocesos).
        $hitos = match ($hasta) {
            InscripcionEstatus::DATA_COMPLETE => ['datos_completados_at'],
            InscripcionEstatus::PAYMENT_VALIDATED => ['pagos_validados_at'],
            InscripcionEstatus::FINAL_REVIEW => ['documentos_validados_at'],
            InscripcionEstatus::ENROLLED => ['finalizada_at'],
            default => [],
        };

        foreach ($hitos as $hito) {
            if ($inscripcion->{$hito} === null) {
                $campos[$hito] = now();
            }
        }

        if ($hasta === InscripcionEstatus::ENROLLED && $usuario !== null) {
            $campos['validada_por'] = $usuario->getKey();   // revisión final responsable
        }

        if ($hasta === InscripcionEstatus::CANCELLED) {
            $campos['cancelada_por'] = $usuario?->getKey();
            $campos['cancelada_at'] = now();
            $campos['cancelacion_motivo'] = $motivo;
        }

        return $campos;
    }
}
