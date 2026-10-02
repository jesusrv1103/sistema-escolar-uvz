<?php

namespace App\Services;

use App\Enums\EstatusConvenio;
use App\Enums\EstatusConceptoPago;
use App\Enums\InscripcionEstatus;
use App\Enums\TipoConcepto;
use App\Exceptions\CargosInvalidosException;
use App\Exceptions\ConvenioInvalidoException;
use App\Exceptions\MotivoRequeridoException;
use App\Exceptions\TransicionInvalidaException;
use App\Models\Inscripcion;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Único punto autorizado para modificar `inscripciones.estatus` (§7.3.6).
 *
 * Toda transición:
 *  1. se valida contra la matriz única `InscripcionEstatus::TRANSICIONES` (§7.3.1);
 *  2. exige motivo cuando corresponde (retrocesos y cancelaciones, §7.3.2);
 *  3. ocurre dentro de una transacción de BD (§7.3.3);
 *  4. registra auditoría y usuario responsable en la misma transacción.
 *
 * Las guardas de negocio de módulos aún inexistentes (pagos, documentos,
 * número de control) NO se simulan: se incorporarán en sus fases (§7.3.5).
 */
class InscripcionService
{
    public function __construct(private readonly AuditoriaService $auditoria)
    {
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
