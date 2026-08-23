<?php

namespace App\Core\Services\WhatsApp;

use Illuminate\Support\Str;

class MockWhatsAppProvider implements WhatsAppProviderInterface
{
    /**
     * Mock send message.
     *
     * @return array{provider_id: string, status: string}
     */
    public function sendMessage(string $recipientPhone, string $content): array
    {
        return [
            'provider_id' => 'wamid.'.Str::random(32),
            'status' => 'sent',
        ];
    }
}
