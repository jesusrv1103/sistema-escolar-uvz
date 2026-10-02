<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Regla de negocio de convenios violada (FC-3, RB-13/RB-17):
 * convenio inactivo/eliminado/fuera de vigencia, más de un convenio por
 * inscripción, etapa inválida o estatus de convenio que no admite la
 * operación solicitada.
 */
class ConvenioInvalidoException extends RuntimeException
{
}
