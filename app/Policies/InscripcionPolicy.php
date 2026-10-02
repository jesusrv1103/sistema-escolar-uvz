<?php

namespace App\Policies;

use App\Models\User;

/**
 * Wizard y ciclo de vida de la inscripción (§8.2).
 * La validación de guardas (RB-1…RB-14) vive en la máquina de estados de la Fase 5.
 */
class InscripcionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('inscripcion.create');
    }

    public function update(User $user): bool
    {
        return $user->can('inscripcion.update');
    }

    public function generarCargos(User $user): bool
    {
        return $user->can('inscripcion.generarCargos');
    }

    public function finalizar(User $user): bool
    {
        return $user->can('inscripcion.finalizar');
    }

    public function cancelar(User $user): bool
    {
        return $user->can('inscripcion.cancelar');
    }
}
