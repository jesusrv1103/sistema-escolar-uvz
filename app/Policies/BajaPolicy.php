<?php

namespace App\Policies;

use App\Models\Baja;
use App\Models\User;

/**
 * Políticas para Bajas Académicas (2.0-11).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: crear, ver, reingreso
 * - ADMINISTRACION: ver
 * - DOCENTE/ADMISIONES/ADMINISTRACION: sin acceso directo
 */
class BajaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol->value, ['CONTROL_ESCOLAR', 'ADMINISTRACION']);
    }

    public function view(User $user, Baja $baja): bool
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

        // Solo CONTROL_ESCOLAR y ADMIN pueden crear bajas
        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function reingreso(User $user, Baja $baja): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo CONTROL_ESCOLAR y ADMIN pueden autorizar reingresos
        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function update(User $user, Baja $baja): bool
    {
        return false; // Bajas no se editan (append-only)
    }

    public function delete(User $user, Baja $baja): bool
    {
        return false; // Bajas no se eliminan
    }
}