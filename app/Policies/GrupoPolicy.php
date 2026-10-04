<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;

/**
 * Políticas para Grupos (solo ADMIN, patrón admin-only vía Gate::before).
 */
class GrupoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Grupo $grupo): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('grupo.create');
    }

    public function update(User $user, Grupo $grupo): bool
    {
        return $user->can('grupo.update');
    }

    public function delete(User $user, Grupo $grupo): bool
    {
        return $user->can('grupo.delete');
    }
}