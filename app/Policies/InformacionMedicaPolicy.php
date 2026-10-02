<?php

namespace App\Policies;

use App\Models\User;

/**
 * RB-18 — Información médica sensible (regla registrada desde la Fase 2):
 *
 *  - ADMIN                 permitido
 *  - ADMISIONES            permitido
 *  - ADMINISTRACION        prohibido (ni lectura)
 *  - CONTROL_ESCOLAR       solo lectura durante la revisión final
 *
 * El módulo médico (modelo `InformacionMedica` y su tabla) se implementa en la
 * Fase 4; aquí queda la regla de autorización sin crear el modelo.
 */
class InformacionMedicaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('informacionMedica.view');
    }

    public function view(User $user): bool
    {
        return $user->can('informacionMedica.view');
    }

    public function create(User $user): bool
    {
        return $user->can('informacionMedica.edit');
    }

    public function update(User $user): bool
    {
        return $user->can('informacionMedica.edit');
    }

    public function delete(User $user): bool
    {
        return $user->can('informacionMedica.edit');
    }
}
