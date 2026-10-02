<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de documentos violada (Fase 10): etapa incompatible,
 * tipo de documento inexistente/inactivo, archivo fuera de formato o tamaño,
 * documento fuera del estado esperado o rechazo con motivo inválido.
 * La UI la traduce en un mensaje claro; nunca deja datos a medias (siempre
 * ocurre dentro de una transacción con ROLLBACK TOTAL y compensación de
 * archivos).
 */
class DocumentoInvalidoException extends RuntimeException
{
}
