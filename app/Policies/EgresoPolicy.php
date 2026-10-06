<?php

namespace App\Policies;

use App\Models\Egreso;
use App\Models\User;

/**
 * Políticas de Egreso interno (2.0-14).
 *
 * Delegadoras de las capacidades de `AuthServiceProvider` (lógica única):
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: verifica y registra el egreso
 * - ADMINISTRACION: sólo lectura
 * - ADMISIONES / DOCENTE / ALUMNO: sin acceso
 *
 * No hay "eliminación" ni deshacer del egreso: su existencia es el estado
 * "EGRESÓ" y la trazabilidad vive en auditoría.
 */
class EgresoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('egreso.ver');
    }

    public function view(User $user, Egreso $egreso): bool
    {
        return $user->can('egreso.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('egreso.crear');
    }

    public function update(User $user, Egreso $egreso): bool
    {
        return false; // Un egreso registrado no se edita
    }

    public function delete(User $user, Egreso $egreso): bool
    {
        return false; // Trazabilidad: no se elimina
    }
}
