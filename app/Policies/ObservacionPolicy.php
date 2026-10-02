<?php

namespace App\Policies;

use App\Models\User;

/**
 * Observaciones (§8.2): los 4 roles crean y resuelven;
 * solo un FALTANTE abierto bloquea la finalización (RB-8).
 */
class ObservacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user): bool
    {
        return $user->activo;
    }

    public function crear(User $user): bool
    {
        return $user->can('observacion.crear');
    }

    public function resolver(User $user): bool
    {
        return $user->can('observacion.resolver');
    }
}
