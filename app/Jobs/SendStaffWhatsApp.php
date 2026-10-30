<?php

namespace App\Jobs;

use App\Models\Organizacao;
use App\Models\StaffWhatsAppDelivery;
use App\Services\Messaging\StaffWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendStaffWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(StaffWhatsAppService $service): void
    {
        $delivery = StaffWhatsAppDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'pending') {
            return;
        }
        if (str_contains($delivery->event_key, ':workout_plan_expired:') && now()->lt(today()->setHour(6))) {
            $this->release(now()->diffInSeconds(today()->setHour(6)));

            return;
        }
        $url = config('services.messaging.baileys.url');
        $token = config('services.messaging.baileys.token');
        if ($service->recipient($delivery->organizacao_id) !== $delivery->recipient || ! $url || ! $token ||
            ! Organizacao::whereKey($delivery->organizacao_id)->where('ativa', true)->exists()) {
            $delivery->update(['status' => 'cancelled', 'error' => 'Piloto desativado ou configuração alterada.']);

            return;
        }
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(35)
            ->post(rtrim($url, '/').'/staff-notifications', [
                'key' => $delivery->event_key, 'to' => $delivery->recipient, 'body' => $delivery->body,
            ]);
        if (in_array($response->status(), [429, 503], true)) {
            throw new \RuntimeException('Ponte WhatsApp temporariamente indisponível.');
        }
        if ($response->status() === 409) {
            $delivery->update(['status' => 'uncertain', 'error' => 'Confira a conversa antes de reenviar.']);

            return;
        }
        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            $delivery->update(['status' => 'failed', 'error' => 'Envio recusado: HTTP '.$response->status()]);

            return;
        }
        $delivery->update(['status' => 'sent', 'provider_message_id' => $response->json('id'), 'sent_at' => now(), 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        StaffWhatsAppDelivery::whereKey($this->deliveryId)->where('status', 'pending')->update([
            'status' => 'uncertain', 'error' => 'Tentativas esgotadas. Confira a conexão e a conversa.',
        ]);
    }
}
