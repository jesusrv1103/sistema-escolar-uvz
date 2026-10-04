<?php

namespace App\Policies;

use App\Models\Acta;
use App\Models\User;

/**
 * Políticas para Actas de Calificaciones (2.0-08).
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: lectura completa, cierra actas
 * - DOCENTE: lectura, crea actas (no cierra)
 * - ADMINISTRACION / ADMISIONES: sin acceso
 *
 * Four-eyes: DOCENTE captura, CONTROL_ESCOLAR cierra.
 */
class ActaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Acta $acta): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR ven todo
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE ve sus grupos
        if ($user->rol->value === 'DOCENTE') {
            if ($user->docente) {
                return $acta->grupo?->docente_id === $user->docente->id;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN, CONTROL_ESCOLAR, DOCENTE (con grupo asignado)
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        if ($user->rol->value === 'DOCENTE') {
            return $user->docente !== null;
        }

        return false;
    }

    public function cerrar(User $user, Acta $acta): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo CONTROL_ESCOLAR y ADMIN pueden cerrar (four-eyes)
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE NO puede cerrar su propia acta
        return false;
    }

    public function update(User $user, Acta $acta): bool
    {
        // Actas no se actualizan directamente; solo se crean y cierran
        return false;
    }

    public function delete(User $user, Acta $acta): bool
    {
        // Solo ADMIN puede eliminar actas
        return $user->esAdmin();
    }
}