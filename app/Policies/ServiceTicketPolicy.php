<?php

namespace App\Policies;

use App\Models\ServiceTicket;
use App\Models\User;

class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->roles()->exists();
    }

    public function view(User $user, ServiceTicket $ticket): bool
    {
        return $user->roles()->where('tenant_id', $ticket->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->roles()->whereIn('role', ['owner', 'manager', 'staff'])->exists();
    }

    public function update(User $user, ServiceTicket $ticket): bool
    {
        return $user->roles()
            ->where('tenant_id', $ticket->tenant_id)
            ->whereIn('role', ['owner', 'manager', 'staff'])
            ->exists();
    }

    public function cancel(User $user, ServiceTicket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
