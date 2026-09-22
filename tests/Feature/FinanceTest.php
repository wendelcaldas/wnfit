<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Assinatura;
use App\Models\Cobranca;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $slug): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => $slug, 'telefone' => '71999990000', 'status' => 'ativo']);

        return [$user, $org, $student];
    }

    private function charge(Aluno $student, string $due, float $value, array $extra = []): Cobranca
    {
        return Cobranca::create([...['organizacao_id' => $student->organizacao_id, 'aluno_id' => $student->id,
            'competencia' => substr($due, 5, 2).'/'.substr($due, 0, 4), 'vencimento' => $due, 'valor' => $value, 'status' => 'pendente'], ...$extra]);
    }

    public function test_monthly_summary_separates_cash_received_and_scopes_all_data(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, , $student] = $this->account('Meu studio');
        [, , $other] = $this->account('Outro studio');
        $this->charge($student, '2026-09-10', 100);
        $this->charge($student, '2026-09-25', 200);
        $old = $this->charge($student, '2026-08-10', 50, ['status' => 'pago']);
        $old->pagamento()->create(['valor' => 50, 'metodo' => 'PIX', 'pago_em' => '2026-09-05']);
        $this->charge($other, '2026-09-01', 900);
        $this->actingAs($user)->getJson('/api/finance?month=2026-09')->assertOk()
            ->assertJsonPath('summary.expected', 300)->assertJsonPath('summary.received', 50)
            ->assertJsonPath('summary.open', 300)->assertJsonPath('summary.overdue', 100)
            ->assertJsonCount(2, 'charges');
        $this->getJson('/api/finance?month=2026-09&status=atrasado')->assertJsonCount(1, 'charges')
            ->assertJsonPath('summary.open', 300);
        $this->getJson('/api/finance?month=2026-09&q=Outro')->assertJsonCount(0, 'charges');
        $this->getJson('/api/finance?month=invalid')->assertUnprocessable();
    }

    public function test_forecast_counts_missing_installments_without_duplicating_existing_charges(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, $org, $student] = $this->account('Previsao');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        $subscription = Assinatura::create(['organizacao_id' => $org->id, 'aluno_id' => $student->id, 'plano_id' => $plan->id,
            'status' => 'ativa', 'inicio_em' => '2026-08-01', 'proximo_vencimento' => '2026-09-30', 'auto_renovacao' => true]);
        $this->actingAs($user)->getJson('/api/finance?month=2026-10')->assertJsonPath('summary.expected', 120)->assertJsonPath('summary.projected', 120);
        $this->charge($student, '2026-10-30', 110, ['assinatura_id' => $subscription->id]);
        $this->getJson('/api/finance?month=2026-10')->assertJsonPath('summary.expected', 110)->assertJsonPath('summary.projected', 0);
        $subscription->update(['status' => 'cancelada']);
        $this->getJson('/api/finance?month=2026-11')->assertJsonPath('summary.expected', 0);
    }

    public function test_manual_draft_can_be_edited_and_sent_without_paying_the_charge_or_calling_provider(): void
    {
        Http::preventStrayRequests();
        config(['services.messaging.mode' => 'manual']);
        [$user, , $student] = $this->account('Mensagens');
        [$otherUser] = $this->account('Outro');
        $charge = $this->charge($student, '2026-09-30', 150, ['link_pagamento' => 'https://pay.wnfit.test/falso']);
        $response = $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send")->assertOk();
        $id = $response->json('charge.lastMessage.id');
        $this->assertStringNotContainsString('pay.wnfit.test', $response->json('charge.lastMessage.content'));
        $this->actingAs($otherUser)->patchJson("/api/messages/$id", ['content' => 'Outro'])->assertNotFound();
        $edit = $this->actingAs($user)->patchJson("/api/messages/$id", ['content' => 'Olá! Podemos combinar o pagamento?'])->assertOk();
        $this->assertStringContainsString(rawurlencode('Olá! Podemos combinar o pagamento?'), $edit->json('message.manualUrl'));
        $this->postJson("/api/messages/$id/sent")->assertOk();
        $this->assertSame('pendente', $charge->fresh()->status);
        $this->assertNotNull($charge->fresh()->enviado_em);
        $this->patchJson("/api/messages/$id", ['content' => 'Mudança'])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_payment_is_idempotent_and_shared_with_student_profile(): void
    {
        [$user, , $student] = $this->account('Pagamento');
        [$other] = $this->account('Isolado');
        $charge = $this->charge($student, '2026-09-30', 150);
        $this->actingAs($other)->postJson("/api/charges/{$charge->id}/pay")->assertNotFound();
        $this->actingAs($user)->postJson("/api/charges/{$charge->id}/pay", ['method' => 'Dinheiro'])->assertOk();
        $this->postJson("/api/charges/{$charge->id}/pay")->assertOk();
        $this->assertSame(1, $charge->pagamento()->count());
        $this->assertDatabaseHas('pagamentos', ['cobranca_id' => $charge->id, 'metodo' => 'Dinheiro']);
        $this->getJson("/api/students/{$student->id}")->assertJsonPath('student.financial.totalPaid', 150)
            ->assertJsonPath('student.financial.openAmount', 0);
        $this->postJson("/api/charges/{$charge->id}/send")->assertUnprocessable();
    }

    public function test_finance_requires_authentication(): void
    {
        $this->getJson('/api/finance')->assertUnauthorized();
    }

    public function test_missing_phone_can_be_saved_while_preparing_a_manual_charge(): void
    {
        Http::preventStrayRequests();
        [$user, , $student] = $this->account('Sem telefone');
        $student->update(['telefone' => null]);
        $charge = $this->charge($student, '2026-09-30', 150);
        $url = "/api/charges/{$charge->id}/send";
        $this->actingAs($user)->postJson($url, ['manual' => true])->assertUnprocessable()->assertJsonValidationErrors('telefone');
        $this->assertDatabaseCount('mensagens', 0);
        $this->postJson($url, ['manual' => true, 'telefone' => '123'])->assertUnprocessable()->assertJsonValidationErrors('telefone');
        $this->assertNull($student->fresh()->telefone);
        [$other] = $this->account('Outro telefone');
        $this->actingAs($other)->postJson($url, ['manual' => true, 'telefone' => '(55) 99999-1234'])->assertNotFound();
        $this->assertNull($student->fresh()->telefone);
        $response = $this->actingAs($user)->postJson($url, ['manual' => true, 'telefone' => '(55) 99999-1234'])->assertOk()
            ->assertJsonPath('charge.lastMessage.status', 'manual_preparado');
        $this->assertSame('+5555999991234', $student->fresh()->telefone);
        $this->assertStringStartsWith('https://wa.me/5555999991234?', $response->json('charge.lastMessage.manualUrl'));
        $this->assertNull($charge->fresh()->enviado_em);
        Http::assertNothingSent();
    }

    public function test_student_payment_preserves_the_selected_method(): void
    {
        [$user, , $student] = $this->account('Formas');
        foreach (['PIX', 'Crédito', 'Débito', 'Dinheiro', 'Transferência', 'Boleto'] as $method) {
            $charge = $this->charge($student, '2026-09-30', 150);
            $this->actingAs($user)->postJson("/api/charges/{$charge->id}/pay", ['method' => $method])->assertOk()
                ->assertJsonPath('charge.paymentMethod', $method)->assertJsonPath('charge.status', 'pago');
            $this->assertDatabaseHas('pagamentos', ['cobranca_id' => $charge->id, 'metodo' => $method]);
            $this->assertDatabaseHas('cobrancas', ['id' => $charge->id, 'forma_pagamento' => $method]);
        }
        $charge = $this->charge($student, '2026-09-30', 150);
        $this->postJson("/api/charges/{$charge->id}/pay", ['method' => 'invalido'])->assertUnprocessable();
        $this->assertNull($charge->pagamento);
    }

    public function test_student_message_action_only_prepares_the_configured_template_even_in_automatic_mode(): void
    {
        Http::preventStrayRequests();
        config(['services.messaging.mode' => 'automatic']);
        [$user, $org, $student] = $this->account('Texto');
        \App\Models\MensagemConfiguracao::create([
            'organizacao_id' => $org->id, 'charge_message_body' => 'Ola {aluno}, mensalidade de {valor} com vencimento {vencimento}.',
            'charge_template_key' => 'custom', 'ativo' => true,
        ]);
        $charge = $this->charge($student, '2026-09-30', 150);
        $this->actingAs($user)->postJson("/api/charges/{$charge->id}/send", ['manual' => true])->assertOk()
            ->assertJsonPath('charge.lastMessage.provider', 'whatsapp_link')
            ->assertJsonPath('charge.lastMessage.status', 'manual_preparado')
            ->assertJsonPath('charge.lastMessage.content', 'Ola Texto, mensalidade de R$ 150,00 com vencimento 30/09/2026.')
            ->assertJsonPath('charge.sentAt', null);
        Http::assertNothingSent();
    }
}
