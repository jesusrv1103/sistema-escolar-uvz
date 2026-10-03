<?php

namespace App\Policies;

use App\Models\PlanEstudio;
use App\Models\User;

/**
 * Planes de estudio — solo ADMIN (capacidades admin-only, Gate::before).
 */
class PlanEstudioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, PlanEstudio $plan): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->can('plan_estudio.create');
    }

    public function update(User $user, PlanEstudio $plan): bool
    {
        return $user->can('plan_estudio.update');
    }

    public function delete(User $user, PlanEstudio $plan): bool
    {
        return $user->can('plan_estudio.delete');
    }
}