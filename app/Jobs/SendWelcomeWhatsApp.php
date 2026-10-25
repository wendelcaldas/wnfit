<?php

namespace App\Jobs;

use App\Models\Mensagem;
use App\Models\MensagemConfiguracao;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendWelcomeWhatsApp implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public int $messageId) {}

    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(): void
    {
        $message = Mensagem::find($this->messageId);
        if (! $message || $message->provedor !== 'baileys' || $message->tipo !== 'boas_vindas' || $message->status !== 'pendente') {
            return;
        }
        $allowed = array_map('trim', config('services.messaging.baileys.organization_ids', []));
        $active = MensagemConfiguracao::where('organizacao_id', $message->organizacao_id)->value('ativo');
        $url = config('services.messaging.baileys.url');
        $token = config('services.messaging.baileys.token');
        if (config('services.messaging.welcome_driver') !== 'baileys' || ! in_array((string) $message->organizacao_id, $allowed, true) || ! $active || ! $url || ! $token) {
            $message->update(['status' => 'falhou', 'erro' => 'Envio desativado ou ponte WhatsApp nao configurada.']);

            return;
        }

        // Same key on every attempt, including timeouts and worker restarts.
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(35)
            ->post(rtrim($url, '/').'/welcome', [
                'key' => 'welcome:'.$message->organizacao_id.':'.$message->aluno_id,
                'to' => $message->destinatario,
                'body' => $message->conteudo,
            ]);
        if (in_array($response->status(), [429, 503], true)) {
            throw new \RuntimeException('Ponte WhatsApp temporariamente indisponivel.');
        }
        if ($response->status() === 409) {
            $message->update(['status' => 'incerto', 'erro' => 'Resultado incerto. Confira a conversa antes de qualquer novo envio.']);

            return;
        }
        if (! $response->successful() || ! is_string($response->json('id')) || $response->json('id') === '') {
            $message->update(['status' => 'falhou', 'erro' => 'Ponte WhatsApp recusou o envio (HTTP '.$response->status().').']);

            return;
        }
        $message->update([
            'status' => 'enviado', 'provider_message_id' => $response->json('id'),
            'enviado_em' => now(), 'erro' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Mensagem::whereKey($this->messageId)->where('status', 'pendente')->update([
            'status' => 'incerto',
            'erro' => 'Tentativas esgotadas. Confira a ponte e a conversa antes de reenviar.',
        ]);
    }
}
