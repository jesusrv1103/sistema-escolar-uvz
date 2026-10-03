<?php

namespace App\Enums;

use App\Concerns\HasEnumLabel;

/**
 * Tipo de prerrequisito entre materias.
 *
 * OBLIGATORIO  → Bloquea inscripción si no está APROBADA (2.0-04).
 * RECOMENDADO  → Solo advertencia, no bloquea.
 *
 * NO existe EQUIVALENTE (equivalencias/revalidaciones en 2.0-11).
 */
enum TipoPrerrequisito: string
{
    use HasEnumLabel;

    case OBLIGATORIO  = 'OBLIGATORIO';
    case RECOMENDADO  = 'RECOMENDADO';

    /**
     * Etiquetas legibles en español (Regla §18).
     */
    public function label(): string
    {
        return match ($this) {
            self::OBLIGATORIO  => 'Obligatorio',
            self::RECOMENDADO  => 'Recomendado',
        };
    }
}