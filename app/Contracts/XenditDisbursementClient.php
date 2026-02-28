<?php

namespace App\Contracts;

interface XenditDisbursementClient
{
    /**
     * Create disbursement. Returns external_id and status from Xendit.
     * Throws on network/API error; returns result with status when request accepted.
     *
     * @return array{external_id: string, status: string}
     */
    public function createDisbursement(
        float $amount,
        string $bankCode,
        string $accountNumber,
        string $externalId,
        string $description
    ): array;
}
