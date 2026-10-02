<?php

namespace App\Policies;

use App\Models\User;

/**
 * CRUD de alumnos y contactos: ADMIN y ADMISIONES (§8.2).
 * Lectura para los 4 roles (§8.2: "Lectura de alumnos / inscripciones / pagos / documentos").
 */
class AlumnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user): bool
    {
        return $user->activo;
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
