<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio del número de control violada (Fase 12): secuencia del año
 * agotada (9999 emitidos), fila de `consecutivos` inexistente tras la
 * inicialización o cualquier fallo de la asignación `YYYY####` (RB-9/P2).
 *
 * Siempre ocurre dentro de la transacción de finalización: ROLLBACK TOTAL
 * (nunca queda un número asignado, un consecutivo consumido ni una
 * inscripción `ENROLLED` sin auditoría).
 */
class NumeroControlException extends RuntimeException
{
}
