<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Models\ServiceTicket;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Plan-based monetization: fee per transaction (free) vs subscription-only (pro).
 * No hardcoded fee in TicketService; all policy here.
 */
class MonetizationService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Apply transaction fee for a completed ticket based on tenant plan.
     * Idempotent: one fee per ticket (reference_id = ticket_fee:{id}).
     */
    public function applyTransactionFee(Tenant $tenant, ServiceTicket $ticket): void
    {
        if ($tenant->id !== $ticket->tenant_id) {
            throw new \InvalidArgumentException('Tenant does not match ticket.');
        }

        $referenceId = 'ticket_fee:'.$ticket->id;

        $previous = TenantContext::id();
        TenantContext::set($tenant->id);

        try {
            if (LedgerEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('reference_id', $referenceId)
                ->exists()) {
                return;
            }

            $feeAmount = $this->resolveTransactionFeeAmount($tenant);
            if ($feeAmount <= 0) {
                return;
            }

            $this->ledgerService->createBalancedEntry(
                $referenceId,
                [
                    ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $feeAmount],
                    ['account' => LedgerService::ACCOUNT_PLATFORM_REVENUE, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $feeAmount],
                ],
                'Transaction fee (plan: '.$tenant->plan_type.')',
                ['ticket_id' => $ticket->id]
            );
        } finally {
            TenantContext::set($previous);
        }
    }

    /**
     * Resolve fee amount for this tenant (0 = no fee).
     */
    protected function resolveTransactionFeeAmount(Tenant $tenant): float
    {
        if (! $tenant->isPlanActive()) {
            return 0;
        }

        if ($tenant->isFree()) {
            return (float) Tenant::DEFAULT_FEE_PER_TRANSACTION_FREE;
        }

        if ($tenant->isPro()) {
            return 0;
        }

        if ($tenant->isEnterprise()) {
            $custom = $tenant->custom_fee_per_transaction;
            return $custom !== null ? (float) $custom : 0;
        }

        return 0;
    }

    /**
     * Monthly subscription deduction for Pro plan.
     * Debit owner_escrow, credit subscription_revenue.
     * If escrow insufficient, mark tenant subscription_overdue_at and do not create entry.
     *
     * @return bool true if deduction was applied, false if skipped (overdue or insufficient)
     */
    public function deductSubscription(Tenant $tenant): bool
    {
        if ($tenant->plan_type !== Tenant::PLAN_TYPE_PRO || ! $tenant->isPlanActive()) {
            return false;
        }

        $amount = $tenant->custom_subscription_price ?? (float) Tenant::DEFAULT_SUBSCRIPTION_PRICE_PRO;
        if ($amount <= 0) {
            return false;
        }

        return DB::transaction(function () use ($tenant, $amount): bool {
            $balance = $this->ledgerService->getOwnerEscrowBalance($tenant->id);
            if ($balance < $amount) {
                $tenant->update(['subscription_overdue_at' => now()]);
                AuditLog::log(
                    AuditLog::ACTION_SUBSCRIPTION_DEDUCTION_OVERDUE,
                    Tenant::class,
                    $tenant->id,
                    null,
                    ['balance' => $balance, 'required' => $amount],
                    ['tenant_id' => $tenant->id]
                );
                return false;
            }

            $previous = TenantContext::id();
            TenantContext::set($tenant->id);

            try {
                $referenceId = 'subscription:'.$tenant->id.':'.now()->format('Y-m');
                if (LedgerEntry::query()->where('tenant_id', $tenant->id)->where('reference_id', $referenceId)->exists()) {
                    return false;
                }

                $this->ledgerService->createBalancedEntry(
                    $referenceId,
                    [
                        ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $amount],
                        ['account' => LedgerService::ACCOUNT_SUBSCRIPTION_REVENUE, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $amount],
                    ],
                    'Pro subscription (monthly)',
                    ['tenant_id' => $tenant->id]
                );

                AuditLog::log(
                    AuditLog::ACTION_SUBSCRIPTION_DEDUCTION,
                    Tenant::class,
                    $tenant->id,
                    null,
                    ['amount' => $amount, 'reference_id' => $referenceId],
                    ['tenant_id' => $tenant->id]
                );
                return true;
            } finally {
                TenantContext::set($previous);
            }
        });
    }
}
