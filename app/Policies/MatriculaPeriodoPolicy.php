<?php

namespace App\Policies;

use App\Models\MatriculaPeriodo;
use App\Models\User;

/**
 * Políticas para Matrícula por Periodo (solo ADMIN, patrón admin-only vía Gate::before).
 */
class MatriculaPeriodoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, MatriculaPeriodo $matricula): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('matricula.create');
    }

    public function update(User $user, MatriculaPeriodo $matricula): bool
    {
        return $user->can('matricula.update');
    }

    public function delete(User $user, MatriculaPeriodo $matricula): bool
    {
        return $user->can('matricula.delete');
    }
}