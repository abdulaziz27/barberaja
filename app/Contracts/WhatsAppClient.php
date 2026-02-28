<?php

namespace App\Contracts;

interface WhatsAppClient
{
    /**
     * Send a WhatsApp message to the given phone number.
     *
     * @param  string  $phone  Phone number in international format (e.g. 6281234567890)
     * @param  string  $message  Plain text message body
     * @return bool True if the message was accepted by the provider, false otherwise
     *
     * @throws \RuntimeException on unrecoverable provider errors
     */
    public function send(string $phone, string $message): bool;
}
