<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de pagos violada (Fase 8): concepto no liquidable,
 * intento duplicado, monto distinto, comprobante inválido o folio no
 * aplicable. La UI la traduce en un mensaje claro; nunca deja datos a medias
 * (siempre ocurre dentro de una transacción con ROLLBACK TOTAL).
 */
class PagoInvalidoException extends RuntimeException
{
}
