<?php

namespace App\Policies;

use App\Models\ServicioSocial;
use App\Models\User;

/**
 * Políticas de Servicio Social (2.0-13).
 *
 * Delegadoras de las capacidades de `AuthServiceProvider` (lógica única):
 * - ADMIN: acceso total (Gate::before)
 * - CONTROL_ESCOLAR: crea, actualiza y valida
 * - ADMINISTRACION: sólo lectura
 * - ADMISIONES / DOCENTE / ALUMNO: sin acceso
 *
 * La inmutabilidad tras VALIDADO no vive aquí (regla de dominio): la aplica
 * `ServicioSocialService` con un mensaje explícito.
 */
class ServicioSocialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('servicio_social.ver');
    }

    public function view(User $user, ServicioSocial $servicio): bool
    {
        return $user->can('servicio_social.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('servicio_social.crear');
    }

    public function update(User $user, ServicioSocial $servicio): bool
    {
        return $user->can('servicio_social.actualizar');
    }

    public function validar(User $user, ServicioSocial $servicio): bool
    {
        return $user->can('servicio_social.validar');
    }

    public function delete(User $user, ServicioSocial $servicio): bool
    {
        return false; // No se eliminan registros (trazabilidad)
    }
}
