<?php

namespace App\Policies;

use App\Models\Materia;
use App\Models\User;

/**
 * Materias — solo ADMIN (capacidades admin-only, Gate::before).
 */
class MateriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Materia $materia): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('materia.create');
    }

    public function update(User $user, Materia $materia): bool
    {
        return $user->can('materia.update');
    }

    public function delete(User $user, Materia $materia): bool
    {
        return $user->can('materia.delete');
    }
}