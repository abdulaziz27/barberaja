<?php

namespace App\Services;

use App\Contracts\XenditDisbursementClient;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Models\Tenant;
use App\Models\Withdrawal;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class WithdrawalServiceException extends \RuntimeException {}

class InsufficientBalanceException extends WithdrawalServiceException {}

class WithdrawalService
{
    public function __construct(
        private LedgerService $ledgerService,
        private XenditDisbursementClient $xendit
    ) {}

    /**
     * Request withdrawal: validate balance with lock, insert withdrawal + ledger, call Xendit.
     * Idempotent by request_id. On disbursement failure: reversal entry + status failed + audit.
     */
    public function requestWithdrawal(
        string $requestId,
        string $tenantId,
        float $amount,
        string $bankCode,
        string $accountNumber
    ): Withdrawal {
        $requestId = trim($requestId);
        if ($requestId === '') {
            throw new \InvalidArgumentException('request_id wajib diisi.');
        }
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount harus positif.');
        }

        $existing = Withdrawal::query()->where('request_id', $requestId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $withdrawal = DB::transaction(function () use ($requestId, $tenantId, $amount, $bankCode, $accountNumber): Withdrawal {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenantId);
            $balance = $this->ledgerService->getOwnerEscrowBalance($tenantId);
            if ($balance < $amount) {
                throw new InsufficientBalanceException(
                    'Saldo escrow tidak cukup. Tersedia: '.number_format($balance, 0, ',', '.').', diminta: '.number_format($amount, 0, ',', '.')
                );
            }

            $withdrawal = Withdrawal::query()->create([
                'tenant_id' => $tenantId,
                'request_id' => $requestId,
                'amount' => $amount,
                'status' => Withdrawal::STATUS_PENDING,
                'bank_code' => $bankCode,
                'account_number' => $accountNumber,
            ]);

            TenantContext::set($tenantId);
            $this->ledgerService->createEscrowDebit(
                $requestId,
                $amount,
                'Withdrawal',
                ['withdrawal_id' => $withdrawal->id]
            );

            AuditLog::log(
                AuditLog::ACTION_WITHDRAWAL_REQUEST,
                Withdrawal::class,
                $withdrawal->id,
                null,
                ['amount' => $amount, 'request_id' => $requestId],
                ['tenant_id' => $tenantId]
            );

            return $withdrawal;
        });

        try {
            $result = $this->xendit->createDisbursement(
                $amount,
                $bankCode,
                $accountNumber,
                $requestId,
                'Withdrawal '.$requestId
            );
            $withdrawal->update([
                'external_id' => $result['external_id'] ?? $requestId,
                'status' => ($result['status'] ?? 'PENDING') === 'COMPLETED' ? Withdrawal::STATUS_SUCCESS : Withdrawal::STATUS_PENDING,
            ]);
            if (($result['status'] ?? '') === 'COMPLETED') {
                AuditLog::log(
                    AuditLog::ACTION_WITHDRAWAL_SUCCESS,
                    Withdrawal::class,
                    $withdrawal->id,
                    null,
                    ['amount' => $amount],
                    ['tenant_id' => $tenantId]
                );
            }
        } catch (\Throwable $e) {
            $this->handleDisbursementFailure($withdrawal, $amount, $e->getMessage());
        }

        return $withdrawal->fresh();
    }

    /**
     * Reversal ledger + mark withdrawal failed + audit. Called when Xendit call fails or webhook reports FAILED.
     */
    public function handleDisbursementFailure(Withdrawal $withdrawal, ?float $amount = null, ?string $reason = null): void
    {
        $amount = $amount ?? (float) $withdrawal->amount;
        $requestId = $withdrawal->request_id;

        DB::transaction(function () use ($withdrawal, $amount, $requestId, $reason): void {
            if ($withdrawal->isFailed()) {
                return;
            }

            $tenantId = $withdrawal->tenant_id;
            TenantContext::set($tenantId);

            $reversalRef = 'reversal:'.$requestId;
            if (! LedgerEntry::query()->where('tenant_id', $tenantId)->where('reference_id', $reversalRef)->exists()) {
                $this->ledgerService->createBalancedEntry(
                    $reversalRef,
                    [
                        ['account' => LedgerService::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $amount],
                        ['account' => LedgerService::ACCOUNT_CLEARING, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $amount],
                    ],
                    'Withdrawal reversal (disbursement failed)',
                    ['withdrawal_id' => $withdrawal->id]
                );
            }

            $withdrawal->update([
                'status' => Withdrawal::STATUS_FAILED,
                'failure_reason' => $reason,
            ]);

            AuditLog::log(
                AuditLog::ACTION_WITHDRAWAL_FAILED,
                Withdrawal::class,
                $withdrawal->id,
                null,
                ['amount' => $amount, 'reason' => $reason],
                ['tenant_id' => $tenantId]
            );
        });
    }

    /**
     * Handle disbursement status webhook from Xendit (COMPLETED / FAILED).
     */
    public function handleDisbursementWebhook(string $externalId, string $status): void
    {
        $withdrawal = Withdrawal::query()->where('external_id', $externalId)->orWhere('request_id', $externalId)->first();
        if (! $withdrawal || ! $withdrawal->isPending()) {
            return;
        }

        if (strtoupper($status) === 'COMPLETED') {
            DB::transaction(function () use ($withdrawal): void {
                $withdrawal->update(['status' => Withdrawal::STATUS_SUCCESS]);
                AuditLog::log(
                    AuditLog::ACTION_WITHDRAWAL_SUCCESS,
                    Withdrawal::class,
                    $withdrawal->id,
                    null,
                    ['amount' => (float) $withdrawal->amount],
                    ['tenant_id' => $withdrawal->tenant_id]
                );
            });
            return;
        }

        if (strtoupper($status) === 'FAILED') {
            $this->handleDisbursementFailure($withdrawal, null, 'Webhook: status FAILED');
        }
    }
}
