<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Mensagem;
use App\Models\MensagemConfiguracao;
use App\Models\Organizacao;
use App\Models\User;
use App\Services\Messaging\TwilioWhatsAppDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.messaging.mode' => 'automatic',
            'services.twilio.whatsapp.driver' => 'fake',
            'services.twilio.whatsapp.from' => '+5571000000000',
            'services.twilio.whatsapp.templates.charge_reminder' => null,
            'services.twilio.whatsapp.templates.student_welcome' => null,
            'services.twilio.validate_webhook_signature' => false,
        ]);
    }

    public function test_student_registration_sends_welcome_message_by_whatsapp(): void
    {
        [$user] = $this->organizationWithUser('studio-boas-vindas');

        $response = $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Bianca Ramos',
            'email' => 'bianca@example.com',
            'telefone' => '(71) 99988-7766',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-09-01',
            'metodo_pagamento' => 'PIX',
            'valor_mensal' => 150,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('mensagens', [
            'tipo' => 'boas_vindas',
            'status' => 'queued',
            'destinatario' => '+5571999887766',
            'provedor' => 'fake',
            'conteudo' => 'Ola, Bianca Ramos! Seja bem-vindo(a) ao studio-boas-vindas. Seu plano Plano Mensal ja esta ativo e o primeiro vencimento fica para 01/09/2026.',
        ]);

        $message = Mensagem::query()->where('tipo', 'boas_vindas')->firstOrFail();

        $this->assertNull($message->cobranca_id);
        $this->assertNotNull($message->provider_message_id);
        $this->assertNotNull($message->enviado_em);
    }

    public function test_manual_mode_prepares_welcome_message_when_student_is_registered(): void
    {
        config(['services.messaging.mode' => 'manual']);

        [$user] = $this->organizationWithUser('studio-manual-boas-vindas');

        $response = $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Bianca Ramos',
            'email' => 'bianca@example.com',
            'telefone' => '(71) 99988-7766',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-09-01',
            'metodo_pagamento' => 'PIX',
            'valor_mensal' => 150,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('student.communications.0.type', 'boas_vindas')
            ->assertJsonPath('student.communications.0.status', 'manual_preparado')
            ->assertJsonPath('student.communications.0.provider', 'whatsapp_link');

        $message = Mensagem::query()->where('tipo', 'boas_vindas')->firstOrFail();

        $this->assertSame('manual_preparado', $message->status);
        $this->assertSame('whatsapp_link', $message->provedor);
        $this->assertStringStartsWith('https://wa.me/5571999887766?text=', $message->payload['manual_url']);
        $this->assertSame(
            'Ola, Bianca Ramos! Seja bem-vindo(a) ao studio-manual-boas-vindas. Seu plano Plano Mensal ja esta ativo e o primeiro vencimento fica para 01/09/2026.',
            $message->conteudo,
        );
    }

    public function test_production_welcome_message_requires_approved_template_without_blocking_registration(): void
    {
        config([
            'app.env' => 'production',
            'services.twilio.whatsapp.driver' => 'twilio',
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'secret-token',
            'services.twilio.whatsapp.from' => '+5571000000000',
            'services.twilio.whatsapp.templates.student_welcome' => null,
        ]);

        [$user] = $this->organizationWithUser('studio-producao');

        $response = $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Laura Prado',
            'telefone' => '(71) 98877-6655',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-09-01',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('mensagens', [
            'tipo' => 'boas_vindas',
            'status' => 'falhou',
            'erro' => 'Template aprovado do Twilio WhatsApp nao configurado para mensagens iniciadas pelo sistema.',
        ]);
    }

    public function test_twilio_driver_uses_organization_sender_when_global_sender_is_empty(): void
    {
        config([
            'services.twilio.whatsapp.status_callback_url' => 'https://wnfit.test/api/webhooks/twilio/whatsapp/status',
        ]);

        Http::fake([
            'api.twilio.com/*' => Http::response([
                'sid' => 'SM123',
                'status' => 'queued',
            ], 201),
        ]);

        $driver = new TwilioWhatsAppDriver(
            'AC123',
            'secret-token',
            '',
            (string) config('services.twilio.whatsapp.status_callback_url'),
        );
        $result = $driver->sendTemplate('+5571999887766', 'HX123', ['1' => 'Bianca'], '+5571999990000');

        $this->assertTrue($result->sent);
        $this->assertSame('SM123', $result->providerMessageId);

        Http::assertSent(fn ($request) => $request['From'] === 'whatsapp:+5571999990000'
            && $request['To'] === 'whatsapp:+5571999887766'
            && $request['ContentSid'] === 'HX123'
            && $request['StatusCallback'] === 'https://wnfit.test/api/webhooks/twilio/whatsapp/status');
    }

    public function test_charge_can_be_sent_by_whatsapp_and_records_message_history(): void
    {
        [$user] = $this->organizationWithUser();

        $studentResponse = $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'email' => 'mariana@example.com',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'metodo_pagamento' => 'PIX',
            'valor_mensal' => 150,
        ]);

        $studentResponse->assertCreated();

        $charge = Cobranca::query()->firstOrFail();

        $response = $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send");

        $response
            ->assertOk()
            ->assertJsonPath('charge.lastMessage.status', 'queued')
            ->assertJsonPath('charge.lastMessage.provider', 'fake');

        $this->assertDatabaseHas('mensagens', [
            'cobranca_id' => $charge->id,
            'tipo' => 'cobranca_manual',
            'status' => 'queued',
            'destinatario' => '+5571999991234',
            'provedor' => 'fake',
        ]);

        $this->assertDatabaseHas('cobranca_eventos', [
            'cobranca_id' => $charge->id,
            'tipo' => 'link_enviado',
            'descricao' => 'Mensagem de cobranca enviada pelo WhatsApp.',
        ]);

        $this->assertNotNull(Mensagem::query()->firstOrFail()->enviado_em);
    }

    public function test_charge_send_falls_back_to_manual_whatsapp_link_when_twilio_template_is_not_ready(): void
    {
        config([
            'app.env' => 'production',
            'services.twilio.whatsapp.driver' => 'twilio',
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'secret-token',
            'services.twilio.whatsapp.from' => '+5571000000000',
            'services.twilio.whatsapp.templates.charge_reminder' => null,
        ]);

        [$user] = $this->organizationWithUser('studio-fallback-manual');

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 150,
        ])->assertCreated();

        $charge = Cobranca::query()->firstOrFail();

        $response = $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send");

        $response
            ->assertOk()
            ->assertJsonPath('charge.lastMessage.status', 'manual_preparado')
            ->assertJsonPath('charge.lastMessage.provider', 'whatsapp_link')
            ->assertJsonPath('charge.lastMessage.error', 'Template aprovado do Twilio WhatsApp nao configurado para mensagens iniciadas pelo sistema.');

        $manualUrl = $response->json('charge.lastMessage.manualUrl');

        $this->assertStringStartsWith('https://wa.me/5571999991234?text=', $manualUrl);
        $this->assertStringContainsString('Mariana%20Alves', $manualUrl);
        $this->assertDatabaseHas('cobranca_eventos', [
            'cobranca_id' => $charge->id,
            'tipo' => 'mensagem_manual_preparada',
            'descricao' => 'Mensagem de cobranca preparada para envio manual pelo WhatsApp.',
        ]);
    }

    public function test_manual_mode_prepares_charge_message_without_calling_twilio(): void
    {
        config([
            'services.messaging.mode' => 'manual',
            'services.twilio.whatsapp.driver' => 'twilio',
        ]);

        [$user] = $this->organizationWithUser('studio-manual-cobranca');

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 150,
        ])->assertCreated();

        $charge = Cobranca::query()->firstOrFail();

        $response = $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send");

        $response
            ->assertOk()
            ->assertJsonPath('charge.lastMessage.status', 'manual_preparado')
            ->assertJsonPath('charge.lastMessage.provider', 'whatsapp_link');

        $this->assertStringStartsWith('https://wa.me/5571999991234?text=', $response->json('charge.lastMessage.manualUrl'));
        $this->assertDatabaseHas('cobranca_eventos', [
            'cobranca_id' => $charge->id,
            'tipo' => 'mensagem_manual_preparada',
        ]);
    }

    public function test_manual_message_can_be_opened_and_marked_as_sent(): void
    {
        config(['services.messaging.mode' => 'manual']);

        [$user] = $this->organizationWithUser('studio-manual-status');

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 150,
        ])->assertCreated();

        $message = Mensagem::query()->where('tipo', 'boas_vindas')->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/opened")
            ->assertOk()
            ->assertJsonPath('message.status', 'manual_aberto');

        $message->refresh();
        $this->assertArrayHasKey('opened_at', $message->payload);

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/sent")
            ->assertOk()
            ->assertJsonPath('message.status', 'manual_enviado')
            ->assertJsonPath('message.manualUrl', null);

        $message->refresh();
        $this->assertNotNull($message->enviado_em);
        $this->assertNull($message->erro);
    }

    public function test_marking_manual_charge_message_as_sent_updates_charge_history(): void
    {
        config(['services.messaging.mode' => 'manual']);

        [$user] = $this->organizationWithUser('studio-manual-cobranca-status');

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 150,
        ])->assertCreated();

        $charge = Cobranca::query()->firstOrFail();
        $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send")->assertOk();

        $message = Mensagem::query()
            ->where('cobranca_id', $charge->id)
            ->where('status', 'manual_preparado')
            ->firstOrFail();

        $this->actingAs($user)
            ->postJson("/api/messages/{$message->id}/sent")
            ->assertOk()
            ->assertJsonPath('message.status', 'manual_enviado');

        $charge->refresh();
        $this->assertNotNull($charge->enviado_em);
        $this->assertDatabaseHas('cobranca_eventos', [
            'cobranca_id' => $charge->id,
            'tipo' => 'mensagem_manual_enviada',
        ]);
    }

    public function test_manual_message_status_update_is_scoped_to_user_organization(): void
    {
        config(['services.messaging.mode' => 'manual']);

        [$owner] = $this->organizationWithUser('studio-manual-dono');
        [$otherUser] = $this->organizationWithUser('studio-manual-outra');

        $this->actingAs($owner)->postJson('/api/students', [
            'nome' => 'Mariana Alves',
            'telefone' => '(71) 99999-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 150,
        ])->assertCreated();

        $message = Mensagem::query()->where('tipo', 'boas_vindas')->firstOrFail();

        $this->actingAs($otherUser)
            ->postJson("/api/messages/{$message->id}/sent")
            ->assertNotFound();
    }

    public function test_twilio_status_webhook_updates_message_delivery_status(): void
    {
        [$user] = $this->organizationWithUser('studio-webhook');

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Carlos Lima',
            'telefone' => '(71) 98888-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
        ])->assertCreated();

        $charge = Cobranca::query()->firstOrFail();
        $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send")->assertOk();
        $message = Mensagem::query()->firstOrFail();

        $this->postJson('/api/webhooks/twilio/whatsapp/status', [
            'MessageSid' => $message->provider_message_id,
            'MessageStatus' => 'delivered',
        ])->assertOk()->assertJsonPath('ok', true);

        $message->refresh();

        $this->assertSame('entregue', $message->status);
        $this->assertNotNull($message->entregue_em);
        $this->assertSame('delivered', $message->payload['status_callback']['MessageStatus']);
    }

    public function test_twilio_status_webhook_rejects_invalid_signature_when_enabled(): void
    {
        config([
            'services.twilio.validate_webhook_signature' => true,
            'services.twilio.auth_token' => 'secret-token',
        ]);

        $this->postJson('/api/webhooks/twilio/whatsapp/status', [
            'MessageSid' => 'SM123',
            'MessageStatus' => 'delivered',
        ], [
            'X-Twilio-Signature' => 'invalid',
        ])->assertForbidden();
    }

    public function test_twilio_status_webhook_accepts_valid_signature_for_configured_public_url(): void
    {
        config([
            'services.twilio.validate_webhook_signature' => true,
            'services.twilio.auth_token' => 'secret-token',
            'services.twilio.whatsapp.status_callback_url' => 'https://app.wnfit.test/api/webhooks/twilio/whatsapp/status',
        ]);

        [, $organization] = $this->organizationWithUser('studio-signed-webhook');

        $message = Mensagem::query()->create([
            'organizacao_id' => $organization->id,
            'canal' => 'whatsapp',
            'provedor' => 'twilio',
            'tipo' => 'boas_vindas',
            'status' => 'queued',
            'destinatario' => '+5571999887766',
            'remetente' => '+557140400368',
            'provider_message_id' => 'SMsigned123',
            'conteudo' => 'Ola, teste.',
            'erro' => 'erro anterior',
        ]);

        $payload = [
            'MessageSid' => 'SMsigned123',
            'MessageStatus' => 'delivered',
        ];

        $this->post('/api/webhooks/twilio/whatsapp/status', $payload, [
            'X-Twilio-Signature' => $this->twilioSignature(
                (string) config('services.twilio.whatsapp.status_callback_url'),
                $payload,
                'secret-token',
            ),
        ])->assertOk()->assertJsonPath('ok', true);

        $message->refresh();

        $this->assertSame('entregue', $message->status);
        $this->assertNull($message->erro);
        $this->assertNotNull($message->entregue_em);
    }

    public function test_owner_can_edit_billing_message_settings(): void
    {
        [$user] = $this->organizationWithUser('studio-config');

        $response = $this->actingAs($user)->patchJson('/api/organization/messaging', [
            'whatsapp_from' => '+5571999990000',
            'charge_message_body' => 'Oi {aluno}, seu plano {plano} vence em {vencimento}. Valor: {valor}.',
            'charge_template_sid' => 'HX123',
            'welcome_message_body' => 'Boas-vindas, {aluno}! Seu plano {plano} no {studio} esta ativo.',
            'welcome_template_sid' => 'HX456',
            'ativo' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('settings.whatsappFrom', '+5571999990000')
            ->assertJsonPath('settings.chargeTemplateSid', 'HX123')
            ->assertJsonPath('settings.welcomeTemplateSid', 'HX456')
            ->assertJsonPath('settings.welcomeMessageBody', 'Boas-vindas, {aluno}! Seu plano {plano} no {studio} esta ativo.')
            ->assertJsonPath('preview', 'Oi Mariana Alves, seu plano Plano Mensal vence em 10/08/2026. Valor: R$ 150,00.');

        $this->assertDatabaseHas('mensagem_configuracoes', [
            'whatsapp_from' => '+5571999990000',
            'charge_message_body' => 'Oi {aluno}, seu plano {plano} vence em {vencimento}. Valor: {valor}.',
            'welcome_message_body' => 'Boas-vindas, {aluno}! Seu plano {plano} no {studio} esta ativo.',
        ]);
    }

    public function test_owner_can_fetch_twilio_content_templates_with_approval_status(): void
    {
        config([
            'services.twilio.account_sid' => 'AC123',
            'services.twilio.auth_token' => 'secret-token',
        ]);

        Http::fake([
            'content.twilio.com/*' => Http::response([
                'contents' => [
                    [
                        'sid' => 'HX11111111111111111111111111111111',
                        'friendly_name' => 'wnfit_charge_due_today_ptbr',
                        'language' => 'pt_BR',
                        'types' => [
                            'twilio/text' => [
                                'body' => 'Ola, {{1}}! Sua mensalidade vence hoje.',
                            ],
                        ],
                        'variables' => ['1' => 'Mariana'],
                        'approvals' => [
                            'whatsapp' => [
                                'status' => 'approved',
                                'category' => 'UTILITY',
                                'content_type' => 'twilio/text',
                            ],
                        ],
                        'date_updated' => '2026-08-28T10:00:00Z',
                    ],
                    [
                        'sid' => 'HX22222222222222222222222222222222',
                        'friendly_name' => 'wnfit_student_welcome_ptbr',
                        'language' => 'pt_BR',
                        'types' => [
                            'twilio/text' => [
                                'body' => 'Ola, {{1}}! Seja bem-vindo(a).',
                            ],
                        ],
                        'approvals' => [
                            'whatsapp' => [
                                'status' => 'pending',
                                'category' => 'UTILITY',
                                'content_type' => 'twilio/text',
                            ],
                        ],
                        'date_updated' => '2026-08-24T10:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        [$user] = $this->organizationWithUser('studio-twilio-templates');

        $response = $this->actingAs($user)->getJson('/api/organization/messaging/twilio-templates');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('templates.0.sid', 'HX11111111111111111111111111111111')
            ->assertJsonPath('templates.0.whatsappStatus', 'approved')
            ->assertJsonPath('templates.0.businessInitiatedReady', true)
            ->assertJsonPath('templates.1.whatsappStatus', 'pending')
            ->assertJsonPath('templates.1.businessInitiatedReady', false);

        Http::assertSent(fn ($request) => $request->url() === 'https://content.twilio.com/v1/ContentAndApprovals?PageSize=50');
    }

    public function test_twilio_content_template_lookup_requires_credentials(): void
    {
        config([
            'services.twilio.account_sid' => null,
            'services.twilio.auth_token' => null,
        ]);

        [$user] = $this->organizationWithUser('studio-twilio-missing-credentials');

        $this->actingAs($user)
            ->getJson('/api/organization/messaging/twilio-templates')
            ->assertOk()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'Credenciais do Twilio nao configuradas.')
            ->assertJsonCount(0, 'templates');
    }

    public function test_charge_message_uses_organization_custom_template(): void
    {
        [$user, $organization] = $this->organizationWithUser('studio-template');

        MensagemConfiguracao::query()->create([
            'organizacao_id' => $organization->id,
            'provedor' => 'twilio',
            'whatsapp_from' => '+5571999990000',
            'charge_template_key' => 'charge_reminder_default',
            'charge_message_body' => 'Mensagem do {studio}: {aluno}, pague {valor} ate {vencimento}.',
            'ativo' => true,
        ]);

        $this->actingAs($user)->postJson('/api/students', [
            'nome' => 'Joana Teste',
            'telefone' => '(71) 97777-1234',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
            'data_inicio' => '2026-08-01',
            'data_vencimento' => '2026-08-10',
            'valor_mensal' => 99,
        ])->assertCreated();

        $charge = Cobranca::query()->firstOrFail();
        $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send")->assertOk();

        $this->assertDatabaseHas('mensagens', [
            'cobranca_id' => $charge->id,
            'conteudo' => 'Mensagem do studio-template: Joana Teste, pague R$ 99,00 ate 10/08/2026.',
            'remetente' => '+5571999990000',
        ]);
    }

    private function organizationWithUser(string $slug = 'studio-mensagem'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create([
            'nome_fantasia' => $slug,
            'slug' => $slug,
            'tipo' => 'studio',
            'ativa' => true,
        ]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$user, $organization];
    }

    private function twilioSignature(string $url, array $payload, string $token): string
    {
        ksort($payload);

        $base = $url;
        foreach ($payload as $key => $value) {
            $base .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $base, $token, true));
    }
}
