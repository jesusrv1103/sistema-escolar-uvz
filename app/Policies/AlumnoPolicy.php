<?php

namespace App\Policies;

use App\Models\User;

/**
 * CRUD de alumnos y contactos: ADMIN y ADMISIONES (§8.2).
 * Lectura (`alumno.ver`): ADMISIONES, ADMINISTRACION y CONTROL_ESCOLAR — la
 * fila §8.2 "Lectura de alumnos / inscripciones / pagos / documentos"; con
 * 2.0-15 deja de bastar `$user->activo` (aislamiento de portales).
 */
class AlumnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('alumno.ver');
    }

    public function view(User $user): bool
    {
        return $user->can('alumno.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('alumno.create');
    }

    public function update(User $user): bool
    {
        return $user->can('alumno.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('alumno.delete');
    }
}
