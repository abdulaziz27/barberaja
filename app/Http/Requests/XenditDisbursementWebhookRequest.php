<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class XenditDisbursementWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = config('services.xendit.disbursement_webhook_token');
        if (empty($token)) {
            return true;
        }
        $provided = $this->header('X-CALLBACK-TOKEN');
        return is_string($provided) && hash_equals($token, $provided);
    }

    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:COMPLETED,FAILED,PENDING'],
        ];
    }
}
