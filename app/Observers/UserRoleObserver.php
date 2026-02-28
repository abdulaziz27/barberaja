<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\UserRole;

class UserRoleObserver
{
    public function created(UserRole $role): void
    {
        $this->logRoleChange($role, null, $role->only(['user_id', 'tenant_id', 'outlet_id', 'role']));
    }

    public function updated(UserRole $role): void
    {
        if (! $role->wasChanged(['user_id', 'tenant_id', 'outlet_id', 'role'])) {
            return;
        }
        $old = $role->getOriginal();
        $new = $role->only(['user_id', 'tenant_id', 'outlet_id', 'role']);
        $oldValues = array_intersect_key(
            array_filter($old, fn ($k) => in_array($k, ['user_id', 'tenant_id', 'outlet_id', 'role'], true)),
            array_flip(['user_id', 'tenant_id', 'outlet_id', 'role'])
        );
        $this->logRoleChange($role, $oldValues, $new);
    }

    public function deleted(UserRole $role): void
    {
        $this->logRoleChange($role, $role->only(['user_id', 'tenant_id', 'outlet_id', 'role']), null);
    }

    protected function logRoleChange(UserRole $role, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::log(
            AuditLog::ACTION_ROLE_CHANGE,
            UserRole::class,
            $role->id,
            $oldValues,
            $newValues,
            ['tenant_id' => $role->tenant_id, 'user_id' => $role->user_id]
        );
    }
}
