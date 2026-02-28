<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $user->roles()->where('tenant_id', $product->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->roles()->whereIn('role', ['owner', 'manager'])->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $user->roles()
            ->where('tenant_id', $product->tenant_id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->roles()
            ->where('tenant_id', $product->tenant_id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }
}
