<?php

namespace App\Policies;

use App\Models\PracticaProfesional;
use App\Models\User;

/**
 * Políticas de Prácticas Profesionales (2.0-13).
 *
 * Delegadoras de las capacidades de `AuthServiceProvider` (lógica única):
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: crea, actualiza y valida
 * - ADMINISTRACION: sólo lectura
 * - ADMISIONES / DOCENTE / ALUMNO: sin acceso
 *
 * La inmutabilidad tras VALIDADO la aplica `PracticaProfesionalService`.
 */
class PracticaProfesionalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('practica.ver');
    }

    public function view(User $user, PracticaProfesional $practica): bool
    {
        return $user->can('practica.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('practica.crear');
    }

    public function update(User $user, PracticaProfesional $practica): bool
    {
        return $user->can('practica.actualizar');
    }

    public function validar(User $user, PracticaProfesional $practica): bool
    {
        return $user->can('practica.validar');
    }

    public function delete(User $user, PracticaProfesional $practica): bool
    {
        return false; // No se eliminan registros (trazabilidad)
    }
}
