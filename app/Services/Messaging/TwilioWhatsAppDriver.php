<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwilioWhatsAppDriver implements WhatsAppMessageDriver
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
        private readonly ?string $statusCallbackUrl = null,
    ) {
    }

    public function sendText(string $to, string $body, ?string $from = null): MessageSendResult
    {
        $actualFrom = $from ?: $this->from;

        return $this->send([
            'From' => $this->whatsAppAddress($actualFrom),
            'To' => $this->whatsAppAddress($to),
            'Body' => $body,
        ], $actualFrom);
    }

    public function sendTemplate(string $to, string $contentSid, array $variables, ?string $from = null): MessageSendResult
    {
        $actualFrom = $from ?: $this->from;

        return $this->send([
            'From' => $this->whatsAppAddress($actualFrom),
            'To' => $this->whatsAppAddress($to),
            'ContentSid' => $contentSid,
            'ContentVariables' => json_encode($variables),
        ], $actualFrom);
    }

    private function send(array $payload, string $from): MessageSendResult
    {
        if ($this->statusCallbackUrl) {
            $payload['StatusCallback'] = $this->statusCallbackUrl;
        }

        if (! $this->accountSid || ! $this->authToken || ! $from) {
            return new MessageSendResult(
                sent: false,
                status: 'configuration_error',
                error: 'Credenciais do Twilio WhatsApp nao configuradas.',
            );
        }

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->retry(2, 200)
                ->withBasicAuth($this->accountSid, $this->authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json", $payload)
                ->throw()
                ->json();

            return new MessageSendResult(
                sent: true,
                providerMessageId: $response['sid'] ?? null,
                status: $response['status'] ?? 'queued',
                payload: $response,
            );
        } catch (RequestException $exception) {
            return new MessageSendResult(
                sent: false,
                status: 'failed',
                error: $exception->response?->body() ?: $exception->getMessage(),
                payload: $exception->response?->json() ?? [],
            );
        } catch (ConnectionException $exception) {
            return new MessageSendResult(
                sent: false,
                status: 'connection_error',
                error: 'Falha de conexao com a Twilio: '.$exception->getMessage(),
            );
        } catch (Throwable $exception) {
            return new MessageSendResult(
                sent: false,
                status: 'unexpected_error',
                error: 'Falha inesperada ao enviar WhatsApp: '.$exception->getMessage(),
            );
        }
    }

    private function whatsAppAddress(string $phone): string
    {
        return str_starts_with($phone, 'whatsapp:') ? $phone : 'whatsapp:'.$phone;
    }
}
