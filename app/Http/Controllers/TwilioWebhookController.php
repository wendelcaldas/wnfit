<?php

namespace App\Http\Controllers;

use App\Models\Mensagem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

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
        $message->update([
            'status' => $status,
            'erro' => $data['ErrorMessage'] ?? $data['ErrorCode'] ?? $message->erro,
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

        $url = URL::to($request->getRequestUri());
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
}
