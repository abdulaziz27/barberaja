<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Models\LedgerLine;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    public const ACCOUNT_PLATFORM_REVENUE = 'platform_revenue';
    public const ACCOUNT_OWNER_ESCROW = 'owner_escrow';
    public const ACCOUNT_SUBSCRIPTION_REVENUE = 'subscription_revenue';
    public const ACCOUNT_CLEARING = 'clearing';

    /**
     * Create a balanced double-entry ledger entry (insert-only).
     *
     * Lines format:
     * [
     *   ['account' => 'owner_escrow', 'direction' => 'credit', 'amount' => '10000.00', 'metadata' => []],
     *   ['account' => 'clearing', 'direction' => 'debit', 'amount' => '10000.00'],
     * ]
     */
    public function createBalancedEntry(string $referenceId, array $lines, ?string $description = null, array $metadata = []): LedgerEntry
    {
        $tenantId = TenantContext::id();
        if (! $tenantId) {
            throw new \RuntimeException('Tenant context tidak tersedia.');
        }

        $referenceId = trim($referenceId);
        if ($referenceId === '') {
            throw new \InvalidArgumentException('reference_id wajib diisi.');
        }

        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Ledger entry minimal memiliki 2 baris (debit & credit).');
        }

        $debitCents = 0;
        $creditCents = 0;

        $normalizedLines = [];
        foreach ($lines as $idx => $line) {
            if (! is_array($line)) {
                throw new \InvalidArgumentException("Line #{$idx} tidak valid.");
            }

            $account = $line['account'] ?? null;
            $direction = $line['direction'] ?? null;
            $amount = $line['amount'] ?? null;
            $lineMetadata = $line['metadata'] ?? null;

            if (! is_string($account) || trim($account) === '') {
                throw new \InvalidArgumentException("Line #{$idx}: account wajib diisi.");
            }
            $account = trim($account);
            if (! in_array($account, $this->allowedAccounts(), true)) {
                throw new \InvalidArgumentException("Line #{$idx}: account tidak dikenal: {$account}.");
            }

            if (! in_array($direction, [LedgerLine::DIRECTION_DEBIT, LedgerLine::DIRECTION_CREDIT], true)) {
                throw new \InvalidArgumentException("Line #{$idx}: direction harus debit/credit.");
            }

            $normalizedAmount = $this->normalizeAmount($amount);
            $cents = $this->toCents($normalizedAmount);
            if ($cents <= 0) {
                throw new \InvalidArgumentException("Line #{$idx}: amount harus > 0.");
            }

            if ($direction === LedgerLine::DIRECTION_DEBIT) {
                $debitCents += $cents;
            } else {
                $creditCents += $cents;
            }

            if ($lineMetadata !== null && ! is_array($lineMetadata)) {
                throw new \InvalidArgumentException("Line #{$idx}: metadata harus array.");
            }

            $normalizedLines[] = [
                'account' => $account,
                'direction' => $direction,
                'amount' => $normalizedAmount,
                'metadata' => $lineMetadata,
            ];
        }

        if ($debitCents !== $creditCents) {
            throw new \InvalidArgumentException('Ledger entry tidak balance: total debit harus sama dengan total credit.');
        }

        return DB::transaction(function () use ($referenceId, $description, $metadata, $normalizedLines) {
            $entry = LedgerEntry::query()->create([
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            $entry->lines()->createMany($normalizedLines);

            return $entry->fresh(['lines']);
        });
    }

    /**
     * Credit owner escrow (incoming money for owner).
     * Double-entry uses clearing as balancing account.
     */
    public function createEscrowCredit(string $referenceId, $amount, ?string $description = null, array $metadata = []): LedgerEntry
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return $this->createBalancedEntry(
            $referenceId,
            [
                ['account' => self::ACCOUNT_CLEARING, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $normalizedAmount],
                ['account' => self::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $normalizedAmount],
            ],
            $description ?? 'Escrow credit',
            $metadata
        );
    }

    /**
     * Debit owner escrow (outgoing money: withdrawal/refund), balanced by clearing.
     */
    public function createEscrowDebit(string $referenceId, $amount, ?string $description = null, array $metadata = []): LedgerEntry
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        return $this->createBalancedEntry(
            $referenceId,
            [
                ['account' => self::ACCOUNT_OWNER_ESCROW, 'direction' => LedgerLine::DIRECTION_DEBIT, 'amount' => $normalizedAmount],
                ['account' => self::ACCOUNT_CLEARING, 'direction' => LedgerLine::DIRECTION_CREDIT, 'amount' => $normalizedAmount],
            ],
            $description ?? 'Escrow debit',
            $metadata
        );
    }

    /**
     * Owner escrow balance for a tenant (derived from ledger, no stored balance).
     * Credits increase balance, debits decrease.
     */
    public function getOwnerEscrowBalance(string $tenantId): float
    {
        $credits = (float) LedgerLine::query()
            ->where('account', self::ACCOUNT_OWNER_ESCROW)
            ->where('direction', LedgerLine::DIRECTION_CREDIT)
            ->whereHas('entry', fn ($q) => $q->where('tenant_id', $tenantId))
            ->sum('amount');

        $debits = (float) LedgerLine::query()
            ->where('account', self::ACCOUNT_OWNER_ESCROW)
            ->where('direction', LedgerLine::DIRECTION_DEBIT)
            ->whereHas('entry', fn ($q) => $q->where('tenant_id', $tenantId))
            ->sum('amount');

        return round($credits - $debits, 2);
    }

    /**
     * @return array<int, string>
     */
    protected function allowedAccounts(): array
    {
        return [
            self::ACCOUNT_PLATFORM_REVENUE,
            self::ACCOUNT_OWNER_ESCROW,
            self::ACCOUNT_SUBSCRIPTION_REVENUE,
            self::ACCOUNT_CLEARING,
        ];
    }

    /**
     * Normalize numeric amount to a string with 2 decimals.
     */
    protected function normalizeAmount($amount): string
    {
        if ($amount === null) {
            throw new \InvalidArgumentException('amount wajib diisi.');
        }

        if (is_int($amount)) {
            return $amount.'.00';
        }

        if (is_float($amount)) {
            $amount = number_format($amount, 2, '.', '');
        }

        if (! is_string($amount)) {
            $amount = (string) $amount;
        }

        $amount = trim($amount);

        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('amount harus angka dengan maksimal 2 desimal (contoh: 12000 atau 12000.50).');
        }

        if (! str_contains($amount, '.')) {
            return $amount.'.00';
        }

        [$whole, $fraction] = explode('.', $amount, 2);
        $fraction = str_pad($fraction, 2, '0');

        return $whole.'.'.$fraction;
    }

    /**
     * Convert a normalized "123.45" string to cents (12345).
     */
    protected function toCents(string $normalizedAmount): int
    {
        [$whole, $fraction] = explode('.', $normalizedAmount, 2);

        return ((int) $whole * 100) + (int) $fraction;
    }
}

