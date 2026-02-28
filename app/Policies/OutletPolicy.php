<?php

namespace App\Policies;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OutletPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Outlet $outlet): bool
    {
        return $this->hasTenantScope($user, $outlet);
    }

    public function update(User $user, Outlet $outlet): bool
    {
        // Owner can update any outlet in tenant, manager only their outlet.
        return $user->roles()
            ->where('tenant_id', $outlet->tenant_id)
            ->where(function ($q) use ($outlet) {
                $q->where('role', 'owner')
                  ->orWhere(function ($q) use ($outlet) {
                      $q->where('role', 'manager')
                        ->where('outlet_id', $outlet->id);
                  });
            })
            ->exists();
    }

    protected function hasTenantScope(User $user, Outlet $outlet): bool
    {
        return $user->roles()
            ->where('tenant_id', $outlet->tenant_id)
            ->exists();
    }
}

