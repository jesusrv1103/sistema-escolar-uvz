<?php

namespace App\Policies;

use App\Models\User;

/**
 * Documentos (§8.2): los 4 roles cargan y leen; CONTROL_ESCOLAR valida.
 */
class DocumentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user): bool
    {
        return $user->activo;
    }

    public function cargar(User $user): bool
    {
        return $user->can('documento.cargar');
    }

    public function validar(User $user): bool
    {
        return $user->can('documento.validar');
    }
}
