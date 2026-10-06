<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;

/**
 * Políticas para Grupos (gestión admin-only patrón `grupo.*` vía Gate::before;
 * lectura genérica `view`/`viewAny` sólo exige usuario activo porque cada
 * pantalla impone su propia capacidad).
 */
class GrupoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Grupo $grupo): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('grupo.create');
    }

    public function update(User $user, Grupo $grupo): bool
    {
        return $user->can('grupo.update');
    }

    public function delete(User $user, Grupo $grupo): bool
    {
        return $user->can('grupo.delete');
    }

    /**
     * Portal de docente (2.0-15): el DOCENTE sólo ve y captura en sus propios
     * grupos. Para el resto de roles esta ability no aplica (se usa la ruta
     * administrativa y las abilities `grupo.*`); ADMIN siempre pasa por
     * `Gate::before`.
     */
    public function verComoDocente(User $user, Grupo $grupo): bool
    {
        if (!$user->can('docente.grupo.ver')) {
            return false;
        }

        return $user->docente !== null
            && $grupo->docente_id === $user->docente->id;
    }
}