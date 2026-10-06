<?php

namespace App\Enums;

/**
 * Estatus de un proceso de Prácticas Profesionales (2.0-13).
 *
 * Mismos estados que Servicio Social: EN_PROCESO al crear (aún no validado) y
 * VALIDADO sólo por validación explícita de CONTROL_ESCOLAR/ADMIN. Sin
 * transiciones de rechazo/cancelación (no hay fuente que las defina).
 */
enum EstatusPracticaProfesional: string
{
    case EN_PROCESO = 'EN_PROCESO';
    case VALIDADO = 'VALIDADO';

    public function label(): string
    {
        return match ($this) {
            self::EN_PROCESO => 'En proceso',
            self::VALIDADO => 'Validado',
        };
    }
}
