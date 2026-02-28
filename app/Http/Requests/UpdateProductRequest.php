<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:480'],
            'commission_type' => ['required', 'in:'.Product::COMMISSION_NONE.','.Product::COMMISSION_PERCENTAGE.','.Product::COMMISSION_FIXED],
            'commission_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $commissionValue = $this->input('commission_value');
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'commission_value' => $commissionValue !== null && $commissionValue !== '' ? (float) $commissionValue : 0,
        ]);
    }
}
