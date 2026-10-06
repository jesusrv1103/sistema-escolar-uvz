<?php

namespace App\Policies;

use App\Models\Alumno;
use App\Models\DocumentoGenerado;
use App\Models\User;
use App\Enums\TipoFormato;

/**
 * Políticas para Documentos Generados (formatos académicos).
 *
 * Acceso: ADMIN, ADMISIONES, CONTROL_ESCOLAR ven todos.
 * DOCENTE ve los de sus grupos.
 * ADMINISTRACION no ve documentos académicos (solo pagos).
 */
class DocumentoGeneradoPolicy
{
    /**
     * ADMIN, ADMISIONES, CONTROL_ESCOLAR ven todos.
     * DOCENTE ve los de sus grupos.
     * ADMINISTRACION no ve documentos académicos (solo pagos).
     */
    public function view(User $user, DocumentoGenerado $documento): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR ven todo
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // ADMISIONES ve documentos de sus alumnos
        if ($user->rol->value === 'ADMISIONES') {
            return true;
        }

        // DOCENTE ve documentos de sus grupos
        if ($user->rol->value === 'DOCENTE') {
            if ($documento->grupo_id && $user->docente) {
                return $documento->grupo?->docente_id === $user->docente->id;
            }
        }

        // ALUMNO (2.0-16) ve sólo los documentos generados a su propio nombre
        if ($user->rol->value === 'ALUMNO') {
            return $documento->alumno_id !== null
                && $user->alumno !== null
                && $documento->alumno_id === $user->alumno->id;
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function create(User $user, TipoFormato $tipo = null, Alumno $alumno = null): bool
    {
        if (!$user->activo) {
            return false;
        }

        // ADMIN y CONTROL_ESCOLAR pueden crear cualquier documento
        if ($user->esAdmin() || $user->rol->value === 'CONTROL_ESCOLAR') {
            return true;
        }

        // ADMISIONES puede crear documentos de inscripción
        if ($user->rol->value === 'ADMISIONES') {
            return true;
        }

        // DOCENTE puede crear horarios y listas de sus grupos
        if ($user->rol->value === 'DOCENTE') {
            return $user->docente !== null;
        }

        return false;
    }

    public function update(User $user, DocumentoGenerado $documento): bool
    {
        return false; // Los documentos generados no se modifican
    }

    public function delete(User $user, DocumentoGenerado $documento): bool
    {
        return false; // No se eliminan, son trazabilidad
    }
}
