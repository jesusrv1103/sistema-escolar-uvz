<?php

namespace App\Policies;

use App\Models\Asistencia;
use App\Models\User;

/**
 * Políticas para Asistencia (2.0-07).
 *
 * Reglas:
 * - ADMIN: acceso total
 * - CONTROL_ESCOLAR: lectura y registro completo
 * - DOCENTE: solo sus grupos
 * - ADMINISTRACION / ADMISIONES: sin acceso
 */
class AsistenciaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Asistencia $asistencia): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR ven todo
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE ve solo sus grupos
        if ($user->rol->value === 'DOCENTE') {
            $inscripcion = $asistencia->inscripcionMateria;
            $grupo = $inscripcion?->grupo;
            return $user->docente && $grupo?->docente_id === $user->docente->id;
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

    public function update(User $user, Asistencia $asistencia): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR pueden modificar
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE solo sus grupos
        if ($user->rol->value === 'DOCENTE') {
            $inscripcion = $asistencia->inscripcionMateria;
            $grupo = $inscripcion?->grupo;
            return $user->docente && $grupo?->docente_id === $user->docente->id;
        }

        return false;
    }

    public function delete(User $user, Asistencia $asistencia): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMIN y CONTROL_ESCOLAR pueden eliminar
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        return false;
    }
}