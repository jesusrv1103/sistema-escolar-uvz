<?php

namespace App\Policies;

use App\Models\AjusteFinanciero;
use App\Models\User;

/**
 * Políticas para Ajustes Financieros (ledger de becas) (2.0-12, D-N3).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: ve (solo lectura)
 * - ADMINISTRACION: ve (solo lectura)
 * - Nadie puede crear/editar/eliminar directamente (solo vía servicios)
 */
class AjusteFinancieroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol->value, ['CONTROL_ESCOLAR', 'ADMINISTRACION']);
    }

    public function view(User $user, AjusteFinanciero $ajuste): bool
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
        return false; // Solo se crean vía servicios (BecaAlumnoService, etc.)
    }

    public function update(User $user, AjusteFinanciero $ajuste): bool
    {
        return false; // Append-only
    }

    public function delete(User $user, AjusteFinanciero $ajuste): bool
    {
        return false; // Nunca se eliminan
    }
}