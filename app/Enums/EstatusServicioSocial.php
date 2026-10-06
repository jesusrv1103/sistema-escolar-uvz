<?php

namespace App\Enums;

/**
 * Estatus de un proceso de Servicio Social (2.0-13).
 *
 * Estados aprobados: al crear el registro el proceso queda EN_PROCESO (aún no
 * validado); VALIDADO sólo por validación explícita de CONTROL_ESCOLAR/ADMIN.
 * La ausencia de registro se muestra en UI como "Sin registro". El avance
 * 0–100 se almacena pero NO auto-valida (no existe transición automática).
 */
enum EstatusServicioSocial: string
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
