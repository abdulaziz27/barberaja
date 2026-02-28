<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ServiceTicket::class);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'staff_id' => ['required', 'uuid', 'exists:users,id'],
            'booking_id' => ['nullable', 'uuid', 'exists:bookings,id'],
        ];
    }
}
