<?php

namespace App\Enums;

/** Estatus del documento (§4.12). */
enum EstatusDocumento: string
{
    case PENDIENTE = 'PENDIENTE';
    case ACEPTADO = 'ACEPTADO';
    case RECHAZADO = 'RECHAZADO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::ACEPTADO => 'Aceptado',
            self::RECHAZADO => 'Rechazado',
        };
    }
}
