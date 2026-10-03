<?php

namespace App\Policies;

use App\Models\PlanVersion;
use App\Models\User;

/**
 * Versiones de plan — solo ADMIN (capacidades admin-only, Gate::before).
 */
class PlanVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, PlanVersion $version): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('plan_version.create');
    }

    public function update(User $user, PlanVersion $version): bool
    {
        return $user->can('plan_version.update');
    }

    public function aprobar(User $user, PlanVersion $version): bool
    {
        return $user->can('plan_version.aprobar');
    }

    public function delete(User $user, PlanVersion $version): bool
    {
        return $user->can('plan_version.delete');
    }
}