<?php

namespace App\Jobs;

use App\Models\PlatformSignupNotification;
use App\Services\Messaging\MessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendPlatformSignupWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('platform-signup:'.$this->notificationId))->releaseAfter(10)->expireAfter(60)];
    }

    public function handle(MessagingService $messaging): void
    {
        $notification = PlatformSignupNotification::find($this->notificationId);
        if (! $notification || $notification->status !== 'pending') {
            return;
        }
        try {
            $recipient = $messaging->normalizeBrazilianPhone(config('services.messaging.signup_alerts.recipient'));
        } catch (\Illuminate\Validation\ValidationException) {
            $recipient = null;
        }
        if (! config('services.messaging.signup_alerts.enabled') || $recipient !== $notification->recipient) {
            $notification->update(['status' => 'cancelled', 'error' => 'Canal interno desativado ou destinatário alterado.']);

            return;
        }
        $url = config('services.messaging.baileys.url');
        $token = config('services.messaging.baileys.token');
        if (! $url || ! $token) {
            throw new \RuntimeException('Ponte WhatsApp não configurada.');
        }
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(35)
            ->post(rtrim($url, '/').'/platform-notifications', [
                'key' => 'platform:signup:'.$notification->organizacao_id,
                'to' => $notification->recipient, 'body' => $notification->body,
            ]);
        if (in_array($response->status(), [429, 503], true)) {
            throw new \RuntimeException('Ponte WhatsApp temporariamente indisponível.');
        }
        if ($response->status() === 409) {
            $notification->update(['status' => 'uncertain', 'error' => 'Confira o WhatsApp antes de reenviar.']);

            return;
        }
        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            $notification->update(['status' => 'failed', 'error' => 'Envio recusado: HTTP '.$response->status()]);

            return;
        }
        $notification->update(['status' => 'sent', 'provider_message_id' => $response->json('id'), 'sent_at' => now(), 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        PlatformSignupNotification::whereKey($this->notificationId)->where('status', 'pending')
            ->update(['status' => 'uncertain', 'error' => 'Tentativas esgotadas. Confira a ponte e a conversa.']);
    }
}
