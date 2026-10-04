<?php

namespace App\Policies;

use App\Models\InscripcionMateria;
use App\Models\User;

/**
 * Políticas para Inscripción a Materias (solo ADMIN, patrón admin-only vía Gate::before).
 */
class InscripcionMateriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, InscripcionMateria $inscripcion): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('inscripcion_materia.create');
    }

    public function update(User $user, InscripcionMateria $inscripcion): bool
    {
        return $user->can('inscripcion_materia.update');
    }

    public function delete(User $user, InscripcionMateria $inscripcion): bool
    {
        return $user->can('inscripcion_materia.delete');
    }
}