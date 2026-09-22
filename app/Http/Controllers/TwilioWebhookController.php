<?php

namespace App\Http\Controllers;

use App\Models\Mensagem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwilioWebhookController extends Controller
{
    public function whatsappStatus(Request $request): JsonResponse
    {
        $this->validateTwilioSignature($request);

        $data = $request->validate([
            'MessageSid' => ['required', 'string'],
            'MessageStatus' => ['required', 'string'],
            'ErrorCode' => ['nullable', 'string'],
            'ErrorMessage' => ['nullable', 'string'],
        ]);

        $message = Mensagem::query()
            ->where('provider_message_id', $data['MessageSid'])
            ->first();

        if (! $message) {
            return response()->json(['ok' => true, 'ignored' => true]);
        }

        $status = $this->statusFromTwilio($data['MessageStatus']);
        $failed = in_array($status, ['falhou'], true);

        $message->update([
            'status' => $status,
            'erro' => $failed ? ($data['ErrorMessage'] ?? $data['ErrorCode'] ?? $message->erro) : null,
            'entregue_em' => in_array($status, ['entregue', 'lido'], true) ? now() : $message->entregue_em,
            'payload' => [
                ...($message->payload ?? []),
                'status_callback' => $request->all(),
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    private function statusFromTwilio(string $status): string
    {
        return match ($status) {
            'sent' => 'enviado',
            'delivered' => 'entregue',
            'read' => 'lido',
            'failed', 'undelivered' => 'falhou',
            'queued', 'accepted', 'sending' => 'queued',
            default => $status,
        };
    }

    private function validateTwilioSignature(Request $request): void
    {
        if (! config('services.twilio.validate_webhook_signature')) {
            return;
        }

        $signature = (string) $request->header('X-Twilio-Signature');
        $token = (string) config('services.twilio.auth_token');

        if (! $signature || ! $token) {
            throw new AuthorizationException('Assinatura Twilio ausente.');
        }

        $url = $this->signatureUrl($request);
        $data = $request->post();
        ksort($data);

        $payload = $url;
        foreach ($data as $key => $value) {
            $payload .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $payload, $token, true));

        if (! hash_equals($expected, $signature)) {
            throw new AuthorizationException('Assinatura Twilio invalida.');
        }
    }

    private function signatureUrl(Request $request): string
    {
        $configuredUrl = (string) config('services.twilio.whatsapp.status_callback_url');

        if ($configuredUrl) {
            return $configuredUrl;
        }

        return $request->fullUrl();
    }
}
