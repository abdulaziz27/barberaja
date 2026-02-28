<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Tenant;

class TenantObserver
{
    protected array $planFields = [
        'plan_type', 'plan_status', 'plan_started_at', 'plan_expires_at',
        'custom_fee_per_transaction', 'custom_subscription_price', 'subscription_overdue_at',
    ];

    public function updated(Tenant $tenant): void
    {
        $changes = [];
        foreach ($this->planFields as $key) {
            if ($tenant->wasChanged($key)) {
                $changes[$key] = [
                    'old' => $tenant->getOriginal($key),
                    'new' => $tenant->getAttribute($key),
                ];
            }
        }
        if ($changes !== []) {
            AuditLog::log(
                AuditLog::ACTION_PLAN_CHANGE,
                Tenant::class,
                $tenant->id,
                array_map(fn ($c) => $c['old'], $changes),
                array_map(fn ($c) => $c['new'], $changes),
                ['tenant_id' => $tenant->id]
            );
        }
    }
}
