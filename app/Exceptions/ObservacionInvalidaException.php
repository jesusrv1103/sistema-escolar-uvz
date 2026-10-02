<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de observaciones violada (Fase 11): tipo fuera del
 * BackedEnum, detalle fuera de 5–2000 caracteres, creación sobre una
 * inscripción CANCELLED (estado terminal) o intento de resolver algo que ya
 * está RESUELTA (no hay reapertura en el MVP). La UI la traduce en un
 * mensaje claro; siempre ocurre dentro de una transacción con ROLLBACK
 * TOTAL (nunca quedan datos a medias).
 */
class ObservacionInvalidaException extends RuntimeException
{
}
