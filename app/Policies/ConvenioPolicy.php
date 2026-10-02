<?php

namespace App\Policies;

use App\Models\User;

/**
 * Convenios: ADMISIONES aplica (→ PENDIENTE_APROBACION), ADMINISTRACION aprueba
 * o rechaza (§8.2 y RB-17, principio de doble revisión).
 */
class ConvenioPolicy
{
    public function view(User $user): bool
    {
        return $user->activo;
    }

    public function aplicar(User $user): bool
    {
        return $user->can('convenio.aplicar');
    }

    public function aprobar(User $user): bool
    {
        return $user->can('convenio.aprobar');
    }
}
