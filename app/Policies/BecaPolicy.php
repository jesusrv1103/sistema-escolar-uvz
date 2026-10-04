<?php

namespace App\Policies;

use App\Models\Beca;
use App\Models\User;

/**
 * Políticas para Becas (catálogo) (2.0-12, D-N3).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: crea, actualiza, ve catálogo
 * - ADMINISTRACION: ve
 * - DOCENTE/ADMISIONES: sin acceso
 */
class BecaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol->value, ['CONTROL_ESCOLAR', 'ADMINISTRACION']);
    }

    public function view(User $user, Beca $beca): bool
    {
        if (!$user->activo) {
            return false;
        }

        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        if ($user->rol->value === 'ADMINISTRACION') {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (!$user->activo) {
            return false;
        }

        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function update(User $user, Beca $beca): bool
    {
        if (!$user->activo) {
            return false;
        }

        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function delete(User $user, Beca $beca): bool
    {
        return $user->esAdmin();
    }
}