<?php

namespace App\Enums;

/**
 * Estatus del proceso interno de Titulación (2.0-14).
 *
 * "En proceso" = expediente de titulación abierto; "Titulado" = concluido.
 * No se inventan estados de rechazo/cancelación (no hay fuente que los defina)
 * y no se infiere ninguna escala ni requisito SEP.
 */
enum EstatusTitulacion: string
{
    case EN_TRAMITE = 'EN_TRAMITE';
    case TITULADO = 'TITULADO';

    public function label(): string
    {
        return match ($this) {
            self::EN_TRAMITE => 'En proceso',
            self::TITULADO => 'Titulado',
        };
    }
}
