<?php

namespace App\Services;

use App\Contracts\XenditDisbursementClient as XenditDisbursementClientContract;
use Illuminate\Support\Facades\Http;

class XenditDisbursementClient implements XenditDisbursementClientContract
{
    protected string $baseUrl = 'https://api.xendit.co';

    public function createDisbursement(
        float $amount,
        string $bankCode,
        string $accountNumber,
        string $externalId,
        string $description
    ): array {
        $key = config('services.xendit.secret_key');
        if (empty($key)) {
            throw new \RuntimeException('XENDIT_SECRET_KEY not configured.');
        }

        $response = Http::withBasicAuth($key, '')
            ->post($this->baseUrl.'/disbursements', [
                'amount' => $amount,
                'bank_code' => $bankCode,
                'account_holder_name' => 'Owner',
                'account_number' => $accountNumber,
                'description' => $description,
                'external_id' => $externalId,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Xendit disbursement failed: '.$response->body(),
                $response->status()
            );
        }

        $data = $response->json();
        return [
            'external_id' => $data['external_id'] ?? $externalId,
            'status' => $data['status'] ?? 'PENDING',
        ];
    }
}
