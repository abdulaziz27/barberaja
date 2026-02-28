<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class XenditDpWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expectedToken = config('services.xendit.dp_webhook_token');

        // Jika token belum dikonfigurasi (mis. local dev), jangan blokir.
        if (empty($expectedToken)) {
            return true;
        }

        $providedToken = $this->header('X-CALLBACK-TOKEN');

        return is_string($providedToken) && hash_equals($expectedToken, $providedToken);
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['required', 'string', 'max:255'],
            'booking_id' => ['required', 'uuid', 'exists:bookings,id'],
            'dp_amount' => ['required', 'numeric', 'min:0.01'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->fee_amount === null || $this->fee_amount === '') {
            $this->merge(['fee_amount' => 0]);
        }
    }
}

