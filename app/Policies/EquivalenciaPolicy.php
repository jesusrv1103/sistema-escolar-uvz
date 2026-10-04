<?php

namespace App\Policies;

use App\Models\Equivalencia;
use App\Models\User;

/**
 * Políticas para Equivalencias/Revalidaciones (2.0-11).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: protagonista - crea, actualiza, ve
 * - ADMINISTRACION: ve
 * - DOCENTE/ADMISIONES: sin acceso
 */
class EquivalenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol->value, ['CONTROL_ESCOLAR', 'ADMINISTRACION']);
    }

    public function view(User $user, Equivalencia $equivalencia): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR ven todo
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // ADMINISTRACION ve para consultas
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

        // Solo CONTROL_ESCOLAR y ADMIN pueden crear equivalencias
        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function update(User $user, Equivalencia $equivalencia): bool
    {
        if (!$user->activo) {
            return false;
        }

        // CONTROL_ESCOLAR y ADMIN pueden actualizar
        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function delete(User $user, Equivalencia $equivalencia): bool
    {
        return false; // Equivalencias no se eliminan (append-only)
    }
}