<?php

namespace App\Policies;

use App\Models\Calificacion;
use App\Models\User;

/**
 * Políticas para Calificaciones.
 *
 * Reglas:
 * - ADMIN: acceso total
 * - CONTROL_ESCOLAR: lectura completa, puede consolidar calificaciones
 * - DOCENTE: solo sus grupos (capturar/actualizar calificaciones)
 * - ADMINISTRACION: sin acceso
 * - ADMISIONES: sin acceso
 */
class CalificacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Calificacion $calificacion): bool
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
            $inscripcion = $calificacion->inscripcionMateria;
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

        // Solo DOCENTE (con grupo asignado), ADMIN, CONTROL_ESCOLAR
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        if ($user->rol->value === 'DOCENTE') {
            return $user->docente !== null;
        }

        return false;
    }

    public function update(User $user, Calificacion $calificacion): bool
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
            return $user->docente
                && $calificacion->evaluacion?->grupo?->docente_id === $user->docente->id;
        }

        return false;
    }

    public function delete(User $user, Calificacion $calificacion): bool
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

    public function consolidate(User $user, \App\Models\InscripcionMateria $inscripcion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo CONTROL_ESCOLAR y ADMIN pueden consolidar
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        return false;
    }
}