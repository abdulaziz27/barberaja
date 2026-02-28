<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\MonetizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeductSubscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Run monthly: debit owner_escrow, credit subscription_revenue for each Pro tenant.
     * If escrow insufficient, tenant is marked subscription_overdue_at.
     */
    public function handle(MonetizationService $monetizationService): void
    {
        $tenants = Tenant::query()
            ->where('plan_type', Tenant::PLAN_TYPE_PRO)
            ->where('plan_status', Tenant::PLAN_STATUS_ACTIVE)
            ->get();

        foreach ($tenants as $tenant) {
            $monetizationService->deductSubscription($tenant);
        }
    }
}
