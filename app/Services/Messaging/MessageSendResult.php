<?php

namespace App\Services\Messaging;

class MessageSendResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $status = null,
        public readonly ?string $error = null,
        public readonly array $payload = [],
    ) {
    }
}
