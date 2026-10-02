<?php

namespace App\Enums;

/** Estatus de la aplicación de un convenio (vive en inscripciones, §4.8). */
enum EstatusConvenio: string
{
    case PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';

    /** Etiqueta visible en español; nunca exponer `->value` al usuario. */
    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE_APROBACION => 'Pendiente de aprobación',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
        };
    }
}
