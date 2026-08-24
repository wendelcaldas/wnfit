<?php

namespace App\Services\Messaging;

use Illuminate\Support\Str;

class FakeWhatsAppDriver implements WhatsAppMessageDriver
{
    public function sendText(string $to, string $body, ?string $from = null): MessageSendResult
    {
        return new MessageSendResult(
            sent: true,
            providerMessageId: 'fake_'.Str::uuid()->toString(),
            status: 'queued',
            payload: [
                'from' => $from,
                'to' => $to,
                'body' => $body,
            ],
        );
    }

    public function sendTemplate(string $to, string $contentSid, array $variables, ?string $from = null): MessageSendResult
    {
        return new MessageSendResult(
            sent: true,
            providerMessageId: 'fake_'.Str::uuid()->toString(),
            status: 'queued',
            payload: [
                'from' => $from,
                'to' => $to,
                'contentSid' => $contentSid,
                'variables' => $variables,
            ],
        );
    }
}
