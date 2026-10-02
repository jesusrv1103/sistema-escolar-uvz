<?php

namespace App\Enums;

/** Tipo de concepto de pago (§4.9). Solo INSCRIPCION mueve el estado de la inscripción. */
enum TipoConcepto: string
{
    case INSCRIPCION = 'INSCRIPCION';
    case MENSUALIDAD = 'MENSUALIDAD';
    case OTRO = 'OTRO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::INSCRIPCION => 'Inscripción',
            self::MENSUALIDAD => 'Mensualidad',
            self::OTRO => 'Otro',
        };
    }
}
