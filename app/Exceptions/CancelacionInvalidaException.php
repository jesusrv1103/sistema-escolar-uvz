<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de cancelación violada (Fase 13): motivo inválido
 * (vacío, solo espacios o fuera de rango) o estatus no cancelable
 * (`ENROLLED`/`CANCELLED` son terminales).
 *
 * La UI la traduce en mensaje claro; siempre ocurre dentro de la
 * transacción de cancelación ⇒ ROLLBACK TOTAL (sin conceptos `CANCELADO`
 * parciales, sin campos `cancelada_*` y sin auditoría).
 */
class CancelacionInvalidaException extends RuntimeException
{
}
