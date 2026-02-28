<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddTicketItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->qty === null || $this->qty === '') {
            $this->merge(['qty' => 1]);
        }
    }
}
