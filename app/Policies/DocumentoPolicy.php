<?php

namespace App\Policies;

use App\Models\Documento;
use App\Models\User;

/**
 * Documentos (§8.2): los 4 roles cargan (`documento.cargar`) y leen;
 * solo CONTROL_ESCOLAR valida/rechaza (`documento.validar`); ADMIN pasa
 * siempre por `Gate::before` (D1). Las capacidades viven en
 * `AuthServiceProvider` (lógica única).
 */
class DocumentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    /** Lectura/descarga del documento (incluye el archivo en disco privado). */
    public function view(User $user, ?Documento $documento = null): bool
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
