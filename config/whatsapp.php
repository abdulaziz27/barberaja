<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Driver
    |--------------------------------------------------------------------------
    | Supported: "null" (no-op/logging), "fonnte", "wablas", "twilio"
    | Set via WHATSAPP_DRIVER in .env
    */
    'driver' => env('WHATSAPP_DRIVER', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Provider Credentials
    |--------------------------------------------------------------------------
    */
    'fonnte' => [
        'token' => env('WHATSAPP_FONNTE_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    */
    'queue' => env('WHATSAPP_QUEUE', 'notifications'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | Max messages per minute per phone number (to avoid spam detection).
    */
    'rate_limit_per_minute' => (int) env('WHATSAPP_RATE_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */
    'max_attempts' => (int) env('WHATSAPP_MAX_ATTEMPTS', 3),
    'retry_after_seconds' => (int) env('WHATSAPP_RETRY_AFTER', 60),

    /*
    |--------------------------------------------------------------------------
    | Message Templates
    |--------------------------------------------------------------------------
    | Use :variable placeholders. Available variables depend on context.
    */
    'templates' => [

        'booking_confirmed' => "Halo :customer_name! 👋\n\nBooking kamu di *:outlet_name* sudah dikonfirmasi.\n\n📅 Tanggal: :date\n⏰ Jam: :time\n💈 Layanan: :service_name\n👤 Kapster: :staff_name\n\nSampai jumpa! 🙌",

        'booking_reminder' => "Halo :customer_name! ⏰\n\nPengingat: kamu punya booking besok di *:outlet_name*.\n\n📅 Tanggal: :date\n⏰ Jam: :time\n💈 Layanan: :service_name\n👤 Kapster: :staff_name\n\nJangan lupa ya! 😊",

        'ticket_completed' => "Terima kasih sudah berkunjung ke *:outlet_name*! 🙏\n\nLayanan: :service_summary\nTotal: Rp :total\n\nSampai jumpa lagi! ✂️",

    ],

];
