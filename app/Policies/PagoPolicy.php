<?php

namespace App\Policies;

use App\Models\User;

/**
 * Pagos (§8.2): ADMISIONES/ADMINISTRACION registran; ADMINISTRACION valida
 * (four-eyes, RB-5/RB-6); comprobante para los 4 roles.
 */
class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user): bool
    {
        return $user->activo;
    }

    public function registrar(User $user): bool
    {
        return $user->can('pago.registrar');
    }

    public function validar(User $user): bool
    {
        return $user->can('pago.validar');
    }

    public function descargarComprobante(User $user): bool
    {
        return $user->activo;
    }
}
