<?php

namespace App\Enums;

/** Método de pago (§4.10). */
enum MetodoPago: string
{
    case EFECTIVO = 'EFECTIVO';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case TARJETA = 'TARJETA';
    case OTRO = 'OTRO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::TARJETA => 'Tarjeta',
            self::OTRO => 'Otro',
        };
    }
}
