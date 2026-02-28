<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use Illuminate\Support\Facades\Log;

/**
 * No-op WhatsApp client used in testing and when no real provider is configured.
 * Logs the message instead of sending it.
 */
class NullWhatsAppClient implements WhatsAppClient
{
    public function send(string $phone, string $message): bool
    {
        Log::channel('whatsapp')->info('WhatsApp (null driver): message not sent.', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
