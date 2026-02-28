<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function view(User $user, Booking $booking): bool
    {
        return $user->roles()->where('tenant_id', $booking->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->roles()->whereIn('role', ['owner', 'manager'])->exists();
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $user->roles()
            ->where('tenant_id', $booking->tenant_id)
            ->whereIn('role', ['owner', 'manager'])
            ->exists();
    }
}
