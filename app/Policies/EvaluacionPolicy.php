<?php

namespace App\Policies;

use App\Models\Evaluacion;
use App\Models\Grupo;
use App\Models\User;

/**
 * Políticas para Evaluaciones.
 *
 * Reglas:
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: lectura completa, puede cerrar evaluaciones
 * - DOCENTE: solo sus grupos (crear, editar BORRADOR, publicar, ver)
 * - ADMINISTRACION: sin acceso
 * - ADMISIONES: sin acceso
 */
class EvaluacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Evaluacion $evaluacion): bool
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
            if ($user->docente && $evaluacion->grupo_id) {
                return $evaluacion->grupo?->docente_id === $user->docente->id;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMIN, CONTROL_ESCOLAR, DOCENTE (con grupo asignado)
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        if ($user->rol->value === 'DOCENTE') {
            return $user->docente !== null; // Debe tener perfil de docente vinculado
        }

        return false;
    }

    public function update(User $user, Evaluacion $evaluacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR pueden actualizar
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE solo sus grupos y solo si está en BORRADOR
        if ($user->rol->value === 'DOCENTE' && $evaluacion->esBorrador()) {
            return $user->docente && $evaluacion->grupo?->docente_id === $user->docente->id;
        }

        return false;
    }

    public function delete(User $user, Evaluacion $evaluacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        // Solo ADMIN y CONTROL_ESCOLAR pueden eliminar (solo BORRADOR)
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return $evaluacion->esBorrador();
        }

        // DOCENTE solo sus grupos y solo BORRADOR
        if ($user->rol->value === 'DOCENTE' && $evaluacion->esBorrador()) {
            return $user->docente && $evaluacion->grupo?->docente_id === $user->docente->id;
        }

        return false;
    }

    public function publish(User $user, Evaluacion $evaluacion): bool
    {
        if (!$user->activo) {
            return false;
        }

        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // DOCENTE solo sus grupos y solo BORRADOR
        if ($user->rol->value === 'DOCENTE' && $evaluacion->esBorrador()) {
            return $user->docente && $evaluacion->grupo?->docente_id === $user->docente->id;
        }

        return false;
    }
}