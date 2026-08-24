<?php

namespace App\Services\Messaging;

interface WhatsAppMessageDriver
{
    public function sendText(string $to, string $body, ?string $from = null): MessageSendResult;

    public function sendTemplate(string $to, string $contentSid, array $variables, ?string $from = null): MessageSendResult;
}
