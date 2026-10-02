<?php

namespace App\Services;

use App\Enums\TipoConsecutivo;
use App\Exceptions\NumeroControlException;
use App\Models\Alumno;
use App\Models\Consecutivo;
use App\Models\Inscripcion;
use Illuminate\Support\Facades\DB;

/**
 * Asignación única del número de control `YYYY####` (RB-9, P2 aprobado).
 *
 * Responsabilidad única: `asignar(Alumno, Inscripcion)`. Todo el trabajo de
 * BD ocurre dentro de `DB::transaction`; cuando `InscripcionService::finalizar()`
 * ya tiene una transacción abierta, esta se anida como SAVEPOINT de LA MISMA
 * transacción de Laravel — nunca produce un commit intermedio que pueda
 * sobrevivir al fallo posterior (FC-7: ROLLBACK TOTAL).
 *
 * Reglas:
 *  - Si el alumno YA tiene `numero_control` se devuelve tal cual: sin
 *    incrementar el consecutivo y sin generar un segundo número (D3).
 *  - `YYYY` sale EXCLUSIVAMENTE de `periodos.fecha_inicio->year` (decisión
 *    Fase 12, RB-9/P2): determinista por periodo, no por la fecha de
 *    finalización.
 *  - Concurrencia: `insert-or-ignore` + `UNIQUE (tipo, anio)` crea la fila
 *    ausente de forma segura ante dos procesos, después `lockForUpdate()`
 *    serializa el incremento dentro de la transacción de finalización.
 *  - Límite 9999 explícito: agotar la secuencia lanza `NumeroControlException`
 *    (sin wrap-around ni silencio).
 *  - `UNIQUE(alumnos.numero_control)` es la última línea de defensa.
 *  - PROHIBIDO `MAX(numero_control)`, `COUNT()` o buscar "el último alumno".
 */
class NumeroControlService
{
    /** 4 dígitos consecutivos: la secuencia anual termina en 9999. */
    private const MAXIMO_ANUAL = 9999;

    /**
     * Asigna (o devuelve ya existente) el número de control del alumno.
     *
     * @return string número `YYYY####` vigente del alumno
     *
     * @throws NumeroControlException si la secuencia del año está agotada o
     *                                la fila de `consecutivos` no puede usarse
     */
    public function asignar(Alumno $alumno, Inscripcion $inscripcion): string
    {
        return DB::transaction(function () use ($alumno, $inscripcion): string {
            // Lock del alumno: serializa asignaciones concurrentes del MISMO
            // alumno (p. ej. dos inscripciones en periodos distintos finalizando
            // a la vez) para que el chequeo "¿ya tiene número?" sea fiable.
            $alumno = Alumno::query()->lockForUpdate()->findOrFail($alumno->getKey());

            // 1) Conservación: si ya tiene NC se devuelve intacto, sin tocar
            //    `consecutivos` (inscripciones posteriores no generan otro).
            if ($alumno->numero_control !== null) {
                return $alumno->numero_control;
            }

            // Año del periodo de ingreso (RB-9/P2): NO la fecha de hoy.
            $anio = (int) $inscripcion->periodo()->firstOrFail()->fecha_inicio->year;

            // 2) Inicialización segura de la fila del año: `INSERT IGNORE`
            //    sobre UNIQUE(tipo, anio) — dos procesos que la crean a la vez
            //    no duplican fila; el perdedor espera y sigue con la existente.
            DB::table('consecutivos')->insertOrIgnore([
                'tipo' => TipoConsecutivo::NUMERO_CONTROL->value,
                'anio' => $anio,
                'ultimo_valor' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3) Lock de la fila del año: único punto de incremento (P2).
            $fila = Consecutivo::query()
                ->where('tipo', TipoConsecutivo::NUMERO_CONTROL->value)
                ->where('anio', $anio)
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                throw new NumeroControlException(
                    'No existe el consecutivo NUMERO_CONTROL del año para bloquearlo.',
                );
            }

            // 4) Agotar 9999 es un error EXPLÍCITO (nunca silencioso).
            if ($fila->ultimo_valor >= self::MAXIMO_ANUAL) {
                throw new NumeroControlException(sprintf(
                    'La secuencia de números de control del año %d está agotada (máximo %04d).',
                    $anio,
                    self::MAXIMO_ANUAL,
                ));
            }

            $fila->ultimo_valor++;
            $fila->save();

            // 5) `YYYY####`: 4 dígitos de año + 4 dígitos con `str_pad` (RB-9).
            $numero = sprintf('%04d%04d', $anio, $fila->ultimo_valor);

            if (strlen($numero) !== 8) {
                throw new NumeroControlException('El número de control generado no tiene exactamente 8 dígitos.');
            }

            // 6) Asignación al alumno: `numero_control` está FUERA de
            //    `$fillable` (no editable por formulario) ⇒ forceFill, y el
            //    `UNIQUE` de BD es la última defensa contra duplicados.
            $alumno->forceFill(['numero_control' => $numero])->save();

            return $numero;
        });
    }
}
