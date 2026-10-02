<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de la revisión final violada (Fase 12): finalización
 * solicitada fuera de `FINAL_REVIEW` o con una guarda RB-8 sin cumplir
 * (concepto `INSCRIPCION` no `PAGADO`, documentos obligatorios sin
 * `ACEPTADO`, observación `FALTANTE` abierta o convenio aplicado que no está
 * `APROBADO`).
 *
 * La UI la traduce en mensaje claro; siempre ocurre dentro de la transacción
 * de finalización ⇒ ROLLBACK TOTAL (sin `ENROLLED`, sin número de control y
 * sin consecutivo consumido).
 */
class FinalizacionInvalidaException extends RuntimeException
{
}
