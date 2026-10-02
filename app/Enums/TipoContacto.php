<?php

namespace App\Enums;

/** Tipo de contacto del alumno (§4.3). */
enum TipoContacto: string
{
    case TUTOR = 'TUTOR';
    case EMERGENCIA = 'EMERGENCIA';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::TUTOR => 'Tutor',
            self::EMERGENCIA => 'Emergencia',
        };
    }
}
