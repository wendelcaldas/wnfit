<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class TwilioContentTemplateService
{
    public function templates(): array
    {
        $accountSid = (string) config('services.twilio.account_sid');
        $authToken = (string) config('services.twilio.auth_token');

        if (! $accountSid || ! $authToken) {
            return [
                'ok' => false,
                'error' => 'Credenciais do Twilio nao configuradas.',
                'templates' => [],
            ];
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(10)
                ->get('https://content.twilio.com/v1/ContentAndApprovals', [
                    'PageSize' => 50,
                ])
                ->throw()
                ->json();

            return [
                'ok' => true,
                'error' => null,
                'templates' => collect($response['contents'] ?? [])
                    ->map(fn (array $content) => $this->templatePayload($content))
                    ->sortByDesc('updatedAt')
                    ->values()
                    ->all(),
            ];
        } catch (RequestException $exception) {
            return [
                'ok' => false,
                'error' => $exception->response?->json('message')
                    ?: $exception->response?->body()
                    ?: 'Nao foi possivel consultar os templates da Twilio.',
                'templates' => [],
            ];
        } catch (ConnectionException $exception) {
            return [
                'ok' => false,
                'error' => 'Falha de conexao com a Twilio: '.$exception->getMessage(),
                'templates' => [],
            ];
        }
    }

    private function templatePayload(array $content): array
    {
        $approvals = $content['approvals'] ?? [];
        $approvalRequests = $content['approval_requests'] ?? [];
        $whatsapp = $approvals['whatsapp'] ?? ($approvalRequests['whatsapp'] ?? $approvalRequests);
        $types = array_keys($content['types'] ?? []);
        $body = Arr::get($content, 'types.twilio/text.body')
            ?? Arr::get($content, 'types.twilio/quick-reply.body')
            ?? Arr::get($content, 'types.twilio/call-to-action.body')
            ?? '';

        return [
            'sid' => $content['sid'] ?? null,
            'name' => $content['friendly_name'] ?? $content['friendlyName'] ?? 'Template sem nome',
            'language' => $content['language'] ?? '-',
            'contentType' => $types[0] ?? ($whatsapp['content_type'] ?? '-'),
            'whatsappStatus' => $whatsapp['status'] ?? null,
            'whatsappCategory' => $whatsapp['category'] ?? null,
            'rejectionReason' => $whatsapp['rejection_reason'] ?? null,
            'body' => $body,
            'variables' => $content['variables'] ?? [],
            'updatedAt' => $content['date_updated'] ?? $content['dateUpdated'] ?? null,
            'businessInitiatedReady' => strtolower((string) ($whatsapp['status'] ?? '')) === 'approved',
        ];
    }
}
