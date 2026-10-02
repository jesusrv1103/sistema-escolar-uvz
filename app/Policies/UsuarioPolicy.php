<?php

namespace App\Policies;

use App\Models\User;

/** Gestión de usuarios: solo ADMIN (§8.2, capacidad `usuario.manage`). */
class UsuarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('usuario.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('usuario.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('usuario.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('usuario.manage');
    }
}
