<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Process DP (down payment) webhook: idempotent ledger insert + booking update.
 * Rule: flow sensitif wajib DB::transaction; ledger via LedgerService only.
 */
class DpPaymentService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Process webhook payload. Idempotent by payment_reference.
     * If already processed, returns without error.
     */
    public function processWebhook(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($data['booking_id']);

            TenantContext::set($booking->tenant_id);

            if (LedgerEntry::query()
                ->where('tenant_id', $booking->tenant_id)
                ->where('reference_id', $data['payment_reference'])
                ->exists()) {
                return;
            }

            $dpAmount = (float) $data['dp_amount'];
            $feeAmount = (float) ($data['fee_amount'] ?? 0);
            $gross = $dpAmount + $feeAmount;

            $lines = [
                [
                    'account' => LedgerService::ACCOUNT_CLEARING,
                    'direction' => LedgerLine::DIRECTION_DEBIT,
                    'amount' => $gross,
                ],
                [
                    'account' => LedgerService::ACCOUNT_OWNER_ESCROW,
                    'direction' => LedgerLine::DIRECTION_CREDIT,
                    'amount' => $dpAmount,
                ],
            ];

            if ($feeAmount > 0) {
                $lines[] = [
                    'account' => LedgerService::ACCOUNT_PLATFORM_REVENUE,
                    'direction' => LedgerLine::DIRECTION_CREDIT,
                    'amount' => $feeAmount,
                ];
            }

            $this->ledgerService->createBalancedEntry(
                $data['payment_reference'],
                $lines,
                'Booking DP payment',
                ['booking_id' => $booking->id]
            );

            $currentDp = (float) $booking->dp_amount;
            $booking->dp_amount = $currentDp + $dpAmount;
            $booking->payment_status = 'paid';
            $booking->save();
        });
    }
}
