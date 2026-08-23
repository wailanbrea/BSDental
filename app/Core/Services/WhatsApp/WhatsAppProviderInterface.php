<?php

namespace App\Core\Services\WhatsApp;

interface WhatsAppProviderInterface
{
    /**
     * Send a WhatsApp message.
     *
     * @return array{provider_id: string, status: string}
     */
    public function sendMessage(string $recipientPhone, string $content): array;
}
