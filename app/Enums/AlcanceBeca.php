<?php

namespace App\Enums;

/**
 * Alcance de la beca sobre los conceptos de pago.
 */
enum AlcanceBeca: string
{
    case INSCRIPCION = 'INSCRIPCION';
    case MENSUALIDADES = 'MENSUALIDADES';
    case AMBOS = 'AMBOS';

    public function label(): string
    {
        return match ($this) {
            self::INSCRIPCION => 'Inscripción',
            self::MENSUALIDADES => 'Mensualidades',
            self::AMBOS => 'Ambos (Inscripción + Mensualidades)',
        };
    }
}