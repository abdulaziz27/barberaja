<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public route — no auth required
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'uuid', 'exists:users,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'start_time' => ['required', 'date', 'after_or_equal:today'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'staff_id.required' => 'Pilih kapster terlebih dahulu.',
            'product_id.required' => 'Pilih layanan terlebih dahulu.',
            'start_time.required' => 'Tanggal & jam mulai wajib diisi.',
            'start_time.after_or_equal' => 'Booking tidak bisa untuk tanggal yang sudah lewat.',
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
        ];
    }
}
