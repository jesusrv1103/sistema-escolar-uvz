<?php

namespace App\Policies;

use App\Models\BecaAlumno;
use App\Models\User;

/**
 * Políticas para Asignaciones de Becas a Alumnos (2.0-12, D-N3).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: asigna (solicita), ve
 * - ADMINISTRACION: aprueba/rechaza, ve
 * - CONTROL_ESCOLAR NO aprueba ni rechaza (four-eyes)
 * - DOCENTE/ADMISIONES: sin acceso
 */
class BecaAlumnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo && in_array($user->rol->value, ['CONTROL_ESCOLAR', 'ADMINISTRACION']);
    }

    public function view(User $user, BecaAlumno $asignacion): bool
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

        // CONTROL_ESCOLAR asigna (solicita), ADMIN también
        return $user->rol->value === 'CONTROL_ESCOLAR' || $user->esAdmin();
    }

    public function update(User $user, BecaAlumno $asignacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMIN puede editar (cambiar estatus, etc.)
        return $user->esAdmin();
    }

    public function aprobar(User $user, BecaAlumno $asignacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMINISTRACION y ADMIN pueden aprobar (four-eyes)
        return $user->rol->value === 'ADMINISTRACION' || $user->esAdmin();
    }

    public function rechazar(User $user, BecaAlumno $asignacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMINISTRACION y ADMIN pueden rechazar (four-eyes)
        return $user->rol->value === 'ADMINISTRACION' || $user->esAdmin();
    }

    public function delete(User $user, BecaAlumno $asignacion): bool
    {
        return false; // No se eliminan asignaciones
    }
}