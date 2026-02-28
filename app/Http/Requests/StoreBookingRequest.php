<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'uuid', 'exists:outlets,id'],
            'staff_id' => ['required', 'uuid', 'exists:users,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'start_time' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
