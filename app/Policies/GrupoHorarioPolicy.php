<?php

namespace App\Policies;

use App\Models\GrupoHorario;
use App\Models\User;

/**
 * Políticas para Horarios de Grupo (solo ADMIN, patrón admin-only vía Gate::before).
 */
class GrupoHorarioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, GrupoHorario $horario): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('grupo_horario.create');
    }

    public function update(User $user, GrupoHorario $horario): bool
    {
        return $user->can('grupo_horario.update');
    }

    public function delete(User $user, GrupoHorario $horario): bool
    {
        return $user->can('grupo_horario.delete');
    }
}