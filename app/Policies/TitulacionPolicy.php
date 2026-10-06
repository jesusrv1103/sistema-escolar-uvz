<?php

namespace App\Policies;

use App\Models\Titulacion;
use App\Models\User;

/**
 * Políticas de Titulación interna (2.0-14).
 *
 * Delegadoras de las capacidades de `AuthServiceProvider` (lógica única):
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: crea y actualiza el expediente
 * - ADMINISTRACION: sólo lectura
 * - ADMISIONES / DOCENTE / ALUMNO: sin acceso
 */
class TitulacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('titulacion.ver');
    }

    public function view(User $user, Titulacion $titulacion): bool
    {
        return $user->can('titulacion.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('titulacion.crear');
    }

    public function update(User $user, Titulacion $titulacion): bool
    {
        return $user->can('titulacion.actualizar');
    }

    public function delete(User $user, Titulacion $titulacion): bool
    {
        return false; // Trazabilidad: no se elimina
    }
}
