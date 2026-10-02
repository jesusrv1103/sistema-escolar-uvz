<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

/**
 * Pagos (§8.2): ADMISIONES/ADMINISTRACION registran (`pago.registrar`);
 * ADMINISTRACION valida y rechaza (`pago.validar`, four-eyes RB-5/RB-6);
 * la lectura, el comprobante cargado y el comprobante PDF institucional
 * (Fase 9) están disponibles para los 4 roles.
 * ADMIN pasa siempre por `Gate::before` (D1); las capacidades viven en
 * `AuthServiceProvider` (lógica única).
 */
class PagoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, ?Pago $pago = null): bool
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

    /** Descarga/visualización del comprobante cargado (archivo privado). */
    public function descargarComprobante(User $user, Pago $pago): bool
    {
        return $user->activo;
    }

    /**
     * Descarga del comprobante institucional PDF (Fase 9): los 4 roles con
     * permiso de LECTURA del pago (matriz §8.2), nunca acceso público.
     */
    public function descargarComprobantePdf(User $user, Pago $pago): bool
    {
        return $this->view($user, $pago);
    }
}
