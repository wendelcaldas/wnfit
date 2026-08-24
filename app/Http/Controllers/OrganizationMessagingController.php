<?php

namespace App\Http\Controllers;

use App\Services\Messaging\MessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMessagingController extends Controller
{
    public function __construct(private readonly MessagingService $messaging)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $settings = $this->messaging->settingsForOrganization($organization);

        return response()->json([
            'settings' => $this->payload($settings),
            'variables' => [
                ['key' => '{aluno}', 'label' => 'Nome do aluno'],
                ['key' => '{studio}', 'label' => 'Nome do studio'],
                ['key' => '{plano}', 'label' => 'Plano contratado'],
                ['key' => '{valor}', 'label' => 'Valor da cobranca'],
                ['key' => '{vencimento}', 'label' => 'Data de vencimento'],
                ['key' => '{link_pagamento}', 'label' => 'Link de pagamento'],
            ],
            'previews' => [
                'charge' => $this->preview($settings->charge_message_body),
                'welcome' => $this->preview($settings->welcome_message_body),
            ],
            'preview' => $this->preview($settings->charge_message_body),
            'readiness' => $this->readiness($settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $this->ensureManager($request);

        $data = $request->validate([
            'whatsapp_from' => ['nullable', 'string', 'max:40'],
            'charge_message_body' => ['required', 'string', 'max:1000'],
            'charge_template_sid' => ['nullable', 'string', 'max:120'],
            'welcome_message_body' => ['required', 'string', 'max:1000'],
            'welcome_template_sid' => ['nullable', 'string', 'max:120'],
            'ativo' => ['sometimes', 'boolean'],
        ]);

        $settings = $this->messaging->settingsForOrganization($organization);
        $settings->update([
            'whatsapp_from' => $data['whatsapp_from'] ?? null,
            'charge_message_body' => $data['charge_message_body'],
            'charge_template_sid' => $data['charge_template_sid'] ?? null,
            'welcome_message_body' => $data['welcome_message_body'],
            'welcome_template_sid' => $data['welcome_template_sid'] ?? null,
            'ativo' => (bool) ($data['ativo'] ?? $settings->ativo),
        ]);

        return response()->json([
            'settings' => $this->payload($settings->refresh()),
            'previews' => [
                'charge' => $this->preview($settings->charge_message_body),
                'welcome' => $this->preview($settings->welcome_message_body),
            ],
            'preview' => $this->preview($settings->charge_message_body),
            'readiness' => $this->readiness($settings),
        ]);
    }

    private function ensureManager(Request $request): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $role = $organization->usuarios()->whereKey($request->user()->id)->first()?->pivot?->papel;

        abort_unless(in_array($role, ['proprietario', 'administrador'], true), 403);
    }

    private function payload($settings): array
    {
        return [
            'provider' => $settings->provedor,
            'whatsappFrom' => $settings->whatsapp_from,
            'chargeTemplateKey' => $settings->charge_template_key,
            'chargeTemplateSid' => $settings->charge_template_sid,
            'chargeMessageBody' => $settings->charge_message_body,
            'welcomeTemplateKey' => $settings->welcome_template_key,
            'welcomeTemplateSid' => $settings->welcome_template_sid,
            'welcomeMessageBody' => $settings->welcome_message_body,
            'active' => $settings->ativo,
        ];
    }

    private function preview(string $template): string
    {
        return strtr($template, [
            '{aluno}' => 'Mariana Alves',
            '{plano}' => 'Plano Mensal',
            '{valor}' => 'R$ 150,00',
            '{vencimento}' => '10/08/2026',
            '{link_pagamento}' => 'https://pay.wnfit.test/exemplo',
            '{studio}' => 'Studio Teste WNFit',
        ]);
    }

    private function readiness($settings): array
    {
        $driver = (string) config('services.twilio.whatsapp.driver');
        $callbackUrl = (string) config('services.twilio.whatsapp.status_callback_url');
        $checks = [
            [
                'key' => 'driver',
                'label' => 'Driver Twilio ativo',
                'ok' => $driver === 'twilio',
                'hint' => 'Use TWILIO_WHATSAPP_DRIVER=twilio em producao.',
            ],
            [
                'key' => 'credentials',
                'label' => 'Credenciais da conta',
                'ok' => filled(config('services.twilio.account_sid')) && filled(config('services.twilio.auth_token')),
                'hint' => 'Configure TWILIO_ACCOUNT_SID e TWILIO_AUTH_TOKEN.',
            ],
            [
                'key' => 'sender',
                'label' => 'Remetente WhatsApp',
                'ok' => filled($settings->whatsapp_from) || filled(config('services.twilio.whatsapp.from')),
                'hint' => 'Informe um WhatsApp Sender aprovado, por exemplo +5571999990000.',
            ],
            [
                'key' => 'welcome_template',
                'label' => 'Template de boas-vindas',
                'ok' => filled($settings->welcome_template_sid) || filled(config('services.twilio.whatsapp.templates.student_welcome')),
                'hint' => 'Informe o Content Template SID aprovado para boas-vindas.',
            ],
            [
                'key' => 'charge_template',
                'label' => 'Template de cobranca',
                'ok' => filled($settings->charge_template_sid) || filled(config('services.twilio.whatsapp.templates.charge_reminder')),
                'hint' => 'Informe o Content Template SID aprovado para cobrancas.',
            ],
            [
                'key' => 'status_callback',
                'label' => 'Status callback HTTPS',
                'ok' => str_starts_with($callbackUrl, 'https://'),
                'hint' => 'Configure TWILIO_WHATSAPP_STATUS_CALLBACK_URL com a URL publica HTTPS.',
            ],
            [
                'key' => 'webhook_signature',
                'label' => 'Assinatura de webhook',
                'ok' => (bool) config('services.twilio.validate_webhook_signature'),
                'hint' => 'Mantenha TWILIO_VALIDATE_WEBHOOK_SIGNATURE=true em producao.',
            ],
        ];

        return [
            'ready' => collect($checks)->every(fn (array $check) => $check['ok']),
            'checks' => $checks,
        ];
    }
}
