<?php

namespace App\Http\Controllers;

use App\Models\Mensagem;
use App\Services\Messaging\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessagingService $messaging) {}

    public function opened(Request $request, Mensagem $message): JsonResponse
    {
        $this->authorizeMessage($request, $message);

        $message = $this->messaging->markManualMessageOpened($message);

        return response()->json(['message' => $this->payload($message)]);
    }

    public function update(Request $request, Mensagem $message): JsonResponse
    {
        $this->authorizeMessage($request, $message);
        $data = $request->validate(['content' => ['required', 'string', 'max:5000']]);
        abort_unless(in_array($message->status, ['manual_preparado', 'manual_aberto'], true), 422, 'Apenas mensagens ainda nao enviadas podem ser editadas.');
        $payload = $message->payload ?? [];
        unset($payload['manual_url']);
        $message->update(['conteudo' => $data['content'], 'payload' => $payload]);

        return response()->json(['message' => $this->payload($message->refresh())]);
    }

    public function sent(Request $request, Mensagem $message): JsonResponse
    {
        $this->authorizeMessage($request, $message);

        $message = $this->messaging->markManualMessageSent($message);

        return response()->json(['message' => $this->payload($message)]);
    }

    private function authorizeMessage(Request $request, Mensagem $message): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();

        abort_unless($message->organizacao_id === $organization->id, 404);
    }

    private function payload(Mensagem $message): array
    {
        return [
            'id' => $message->id,
            'status' => $message->status,
            'provider' => $message->provedor,
            'content' => $message->conteudo,
            'sentAt' => optional($message->enviado_em)->format('d/m/Y H:i'),
            'manualUrl' => in_array($message->status, ['manual_preparado', 'manual_aberto'], true)
                ? $this->messaging->manualWhatsAppUrlForMessage($message)
                : null,
        ];
    }
}
