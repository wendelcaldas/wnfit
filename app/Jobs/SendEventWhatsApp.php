<?php

namespace App\Jobs;

use App\Models\Mensagem;
use App\Services\Messaging\AutomaticMessagingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SendEventWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $messageId) {}

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('message-event:'.$this->messageId))->releaseAfter(10)->expireAfter(60)];
    }

    public function handle(AutomaticMessagingService $messaging): void
    {
        $message = Mensagem::find($this->messageId);
        if (! $message || ! $message->event_key || $message->provedor !== 'baileys' || $message->status !== 'pendente') {
            return;
        }
        try {
            $valid = $messaging->valid($message);
        } catch (ValidationException) {
            $valid = false;
        }
        $url = config('services.messaging.baileys.url');
        $token = config('services.messaging.baileys.token');
        if (! $valid || ! $url || ! $token) {
            $message->update(['status' => 'cancelado', 'erro' => 'Envio desativado, configuração indisponível ou evento não mais válido.']);

            return;
        }
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(35)
            ->post(rtrim($url, '/').'/events', ['key' => $message->event_key, 'to' => $message->destinatario, 'body' => $message->conteudo]);
        if (in_array($response->status(), [429, 503], true)) {
            throw new \RuntimeException('Ponte WhatsApp temporariamente indisponível.');
        }
        if ($response->status() === 409) {
            $message->update(['status' => 'incerto', 'erro' => 'Confira a conversa antes de qualquer novo envio.']);

            return;
        }
        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            $message->update(['status' => 'falhou', 'erro' => 'Ponte WhatsApp recusou o envio (HTTP '.$response->status().').']);

            return;
        }
        $message->update(['status' => 'enviado', 'provider_message_id' => $response->json('id'), 'enviado_em' => now(), 'erro' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        Mensagem::whereKey($this->messageId)->where('status', 'pendente')->update([
            'status' => 'incerto', 'erro' => 'Tentativas esgotadas. Confira a ponte e a conversa antes de reenviar.',
        ]);
    }
}
