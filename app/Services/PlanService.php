<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanService
{
    /**
     * Allowed plan types for self-service upgrade.
     */
    public const UPGRADEABLE_PLANS = [
        Tenant::PLAN_TYPE_FREE,
        Tenant::PLAN_TYPE_PRO,
    ];

    /**
     * Change tenant plan type.
     *
     * Rules:
     * - No retroactive charge (existing ledger entries are not modified)
     * - Audit log is written for every plan change
     * - plan_started_at is set to now
     * - plan_expires_at is set to end of next month for pro (null for free)
     * - subscription_overdue_at is cleared on upgrade
     *
     * @throws \InvalidArgumentException if plan is not valid or same as current
     */
    public function changePlan(Tenant $tenant, string $newPlanType, ?string $changedByUserId = null): Tenant
    {
        if (! in_array($newPlanType, self::UPGRADEABLE_PLANS, true)) {
            throw new \InvalidArgumentException("Plan '{$newPlanType}' tidak valid.");
        }

        if ($tenant->plan_type === $newPlanType) {
            throw new \InvalidArgumentException("Tenant sudah menggunakan plan '{$newPlanType}'.");
        }

        return DB::transaction(function () use ($tenant, $newPlanType, $changedByUserId): Tenant {
            $oldPlanType = $tenant->plan_type;

            $updates = [
                'plan_type' => $newPlanType,
                'plan_status' => Tenant::PLAN_STATUS_ACTIVE,
                'plan_started_at' => Carbon::today(),
                'subscription_overdue_at' => null,
            ];

            if ($newPlanType === Tenant::PLAN_TYPE_PRO) {
                // Pro plan: expires end of next month (first billing cycle)
                $updates['plan_expires_at'] = Carbon::now()->addMonth()->endOfMonth()->toDateString();
            } else {
                // Free plan: no expiry
                $updates['plan_expires_at'] = null;
            }

            $tenant->update($updates);

            AuditLog::log(
                AuditLog::ACTION_PLAN_CHANGE,
                Tenant::class,
                $tenant->id,
                ['plan_type' => $oldPlanType],
                ['plan_type' => $newPlanType],
                [
                    'tenant_id' => $tenant->id,
                    'changed_by' => $changedByUserId,
                ]
            );

            return $tenant->fresh();
        });
    }

    /**
     * Simulate transaction fee for a given plan and number of transactions.
     * Used for the billing page fee comparison.
     */
    public function simulateTransactionFee(string $planType, int $transactionCount): float
    {
        return match ($planType) {
            Tenant::PLAN_TYPE_FREE => $transactionCount * (float) Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE,
            default => 0.0,
        };
    }

    /**
     * Get plan comparison data for the billing page.
     *
     * @return array<string, array{name: string, fee_per_transaction: float, subscription: float, features: string[]}>
     */
    public function getPlanComparison(): array
    {
        return [
            Tenant::PLAN_TYPE_FREE => [
                'name' => 'Free',
                'fee_per_transaction' => (float) Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE,
                'subscription' => 0,
                'features' => [
                    'Booking engine',
                    'POS basic',
                    'Ledger transparan',
                    'Rp 2.000 per tiket selesai',
                ],
                'limitations' => [
                    'Fee per transaksi',
                ],
            ],
            Tenant::PLAN_TYPE_PRO => [
                'name' => 'Pro',
                'fee_per_transaction' => 0,
                'subscription' => (float) Tenant::DEFAULT_SUBSCRIPTION_PRICE_PRO,
                'features' => [
                    'Semua fitur Free',
                    'Tidak ada fee per transaksi',
                    'Subscription Rp 99.000/bulan',
                    'Priority support',
                ],
                'limitations' => [],
            ],
        ];
    }
}
