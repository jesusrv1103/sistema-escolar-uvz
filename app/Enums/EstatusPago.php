<?php

namespace App\Enums;

/** Estatus del pago (§4.10). */
enum EstatusPago: string
{
    case PENDIENTE = 'PENDIENTE';
    case VALIDADO = 'VALIDADO';
    case RECHAZADO = 'RECHAZADO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::VALIDADO => 'Validado',
            self::RECHAZADO => 'Rechazado',
        };
    }
}
