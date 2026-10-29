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
            ->assertJsonPath('summary.expected', 350)->assertJsonPath('summary.received', 50)
            ->assertJsonPath('summary.open', 200)->assertJsonPath('summary.overdue', 100)
            ->assertJsonCount(3, 'charges');
        $this->getJson('/api/finance?month=2026-09&status=atrasado')->assertJsonCount(1, 'charges')
            ->assertJsonPath('summary.open', 200);
        $this->getJson('/api/finance?month=2026-09&q=Outro')->assertJsonCount(0, 'charges');
        $this->getJson('/api/finance?month=invalid')->assertUnprocessable();
    }

    public function test_recurrence_creates_real_monthly_charges_and_preserves_existing_values(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, $org, $student] = $this->account('Recorrencia');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        $subscription = Assinatura::create(['organizacao_id' => $org->id, 'aluno_id' => $student->id, 'plano_id' => $plan->id,
            'status' => 'ativa', 'inicio_em' => '2026-08-01', 'proximo_vencimento' => '2026-09-30', 'auto_renovacao' => true]);
        $existing = $this->charge($student, '2026-10-30', 110, ['assinatura_id' => $subscription->id]);
        $this->actingAs($user)->getJson('/api/finance?month=2026-10')->assertOk()
            ->assertJsonPath('summary.expected', 110)->assertJsonPath('charges.0.status', 'pendente')
            ->assertJsonPath('charges.0.id', $existing->id);
        $this->getJson('/api/finance?month=2026-11')->assertOk()->assertJsonPath('summary.expected', 120);
        $this->getJson('/api/finance?month=2026-11')->assertOk()->assertJsonCount(1, 'charges');
        $this->assertSame(1, $subscription->cobrancas()->where('competencia', '11/2026')->count());
        $subscription->update(['status' => 'cancelada']);
        $this->getJson('/api/finance?month=2026-12')->assertOk()->assertJsonPath('summary.expected', 0);
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

    public function test_dashboard_uses_all_month_payments_and_keeps_charts_independent_of_table_filters(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, , $student] = $this->account('Dashboard');
        [, , $other] = $this->account('Isolado dashboard');
        $this->charge($student, '2026-09-10', 100);
        $this->charge($student, '2026-09-25', 200);
        $this->charge($student, '2026-09-21', 80);
        $this->charge($student, '2026-09-03', 999, ['status' => 'cancelado']);
        $paid = $this->charge($student, '2026-09-12', 120, ['status' => 'pago']);
        $paid->pagamento()->create(['valor' => 120, 'metodo' => 'PIX', 'pago_em' => '2026-09-05 12:00:00']);
        $old = $this->charge($student, '2026-08-12', 50, ['status' => 'pago']);
        $old->pagamento()->create(['valor' => 50, 'metodo' => 'Dinheiro', 'pago_em' => '2026-09-05 17:00:00']);
        $future = $this->charge($student, '2026-10-12', 70, ['status' => 'pago']);
        $future->pagamento()->create(['valor' => 70, 'metodo' => 'PIX', 'pago_em' => '2026-09-30 23:59:59']);
        $otherPaid = $this->charge($other, '2026-09-12', 9000, ['status' => 'pago']);
        $otherPaid->pagamento()->create(['valor' => 9000, 'metodo' => 'PIX', 'pago_em' => '2026-09-05']);
        $outside = $this->charge($student, '2026-07-12', 400, ['status' => 'pago']);
        $outside->pagamento()->create(['valor' => 400, 'metodo' => 'PIX', 'pago_em' => '2026-08-31 23:59:59']);

        $response = $this->actingAs($user)->getJson('/api/finance?month=2026-09&status=atrasado&q=inexistente')->assertOk()
            ->assertJsonCount(0, 'charges')->assertJsonCount(30, 'dashboard.daily')
            ->assertJsonPath('dashboard.daily.0.value', 0)
            ->assertJsonPath('dashboard.daily.4.date', '2026-09-05')
            ->assertJsonPath('dashboard.daily.4.value', 170)
            ->assertJsonPath('dashboard.daily.29.value', 70)
            ->assertJsonPath('dashboard.settlement.paid', 240)
            ->assertJsonPath('dashboard.settlement.pending', 280)
            ->assertJsonCount(30, 'dashboard.daily')
            ->assertJsonPath('dashboard.settlement.overdue', 100)
            ->assertJsonPath('dashboard.counts.all', 7)
            ->assertJsonPath('dashboard.counts.open', 2)
            ->assertJsonPath('dashboard.counts.today', 1)
            ->assertJsonPath('dashboard.counts.overdue', 1)
            ->assertJsonPath('dashboard.counts.paid', 3)
            ->assertJsonPath('summary.received', 240);
        $this->assertEquals($response->json('summary.received'), array_sum(array_column($response->json('dashboard.daily'), 'value')));
        $this->assertEquals(240, array_sum(array_column($response->json('dashboard.daily'), 'paid')));
        $this->assertEquals(380, array_sum(array_column($response->json('dashboard.daily'), 'pending')));
    }

    public function test_dashboard_empty_leap_month_has_daily_zero_values(): void
    {
        [$user] = $this->account('Sem movimento');
        $this->actingAs($user)->getJson('/api/finance?month=2028-02')->assertOk()
            ->assertJsonCount(29, 'dashboard.daily')
            ->assertJsonPath('dashboard.daily.28.date', '2028-02-29')
            ->assertJsonPath('dashboard.daily.28.value', 0)
            ->assertJsonPath('dashboard.counts.all', 0)
            ->assertJsonPath('dashboard.settlement.paid', 0);
    }

    public function test_forecast_lists_real_charges_and_reconciles_without_duplicates(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, $org, $student] = $this->account('Previsao detalhada');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        for ($i = 0; $i < 4; $i++) {
            Assinatura::create(['organizacao_id' => $org->id, 'aluno_id' => $student->id, 'plano_id' => $plan->id,
                'status' => 'ativa', 'inicio_em' => '2026-09-01', 'proximo_vencimento' => '2026-10-31', 'auto_renovacao' => false]);
        }
        $response = $this->actingAs($user)->getJson('/api/finance?month=2026-10&status=previsto')->assertOk()
            ->assertJsonPath('summary.expected', 480)->assertJsonPath('listTotal', 480)
            ->assertJsonCount(4, 'charges')->assertJsonPath('charges.0.status', 'pendente')
            ->assertJsonPath('charges.0.dueDate', '31/10/2026')->assertJsonPath('pagination.total', 4);
        $this->assertEquals(480, array_sum(array_column($response->json('charges'), 'value')));
        $this->assertDatabaseCount('cobrancas', 4);
        $subscription = Assinatura::where('organizacao_id', $org->id)->first();
        $subscription->cobrancas()->where('competencia', '10/2026')->firstOrFail()->update(['valor' => 110]);
        $this->charge($student, '2026-10-01', 999, ['status' => 'cancelado']);
        $this->getJson('/api/finance?month=2026-10&status=previsto')->assertOk()
            ->assertJsonPath('summary.expected', 470)->assertJsonPath('listTotal', 470)
            ->assertJsonCount(4, 'charges');
        $this->getJson('/api/finance?month=2026-10&status=previsto&q=ausente')->assertOk()
            ->assertJsonCount(0, 'charges')->assertJsonPath('listTotal', 0)->assertJsonPath('summary.expected', 470);
    }

    public function test_movements_filter_receipt_month_source_and_pending_status(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 21));
        [$user, , $student] = $this->account('Movimentos');
        $old = $this->charge($student, '2026-08-10', 50, ['status' => 'pago']);
        $old->pagamento()->create(['valor' => 50, 'metodo' => 'PIX', 'pago_em' => '2026-09-05']);
        $this->charge($student, '2026-09-10', 100);
        $this->charge($student, '2026-09-25', 200);
        $this->actingAs($user)->getJson('/api/finance?month=2026-09&movements=1&status=pago&source=direto')
            ->assertOk()->assertJsonCount(1, 'charges')->assertJsonPath('listTotal', 50)
            ->assertJsonPath('charges.0.paidAt', '05/09/2026 00:00');
        $this->getJson('/api/finance?month=2026-09&movements=1&status=aberto')
            ->assertOk()->assertJsonCount(1, 'charges')->assertJsonPath('listTotal', 200)
            ->assertJsonPath('charges.0.status', 'pendente');
        foreach (['wellhub', 'totalpass'] as $source) {
            $this->getJson('/api/finance?month=2026-09&movements=1&source='.$source)
                ->assertOk()->assertJsonCount(0, 'charges')->assertJsonPath('listTotal', 0);
        }
        $this->getJson('/api/finance?source=invalid')->assertUnprocessable();
    }

    public function test_manual_income_is_scoped_idempotent_and_included_in_received_only(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29));
        [$user, $org] = $this->account('Avulsas');
        [$other] = $this->account('Outra conta avulsas');
        $payload = ['description' => 'Aula avulsa', 'value' => 75.50, 'date' => '2026-10-28',
            'method' => 'Dinheiro', 'requestId' => (string) \Illuminate\Support\Str::uuid()];
        $this->actingAs($user)->postJson('/api/finance/income', $payload)->assertCreated()->assertJsonPath('month', '2026-10');
        $this->postJson('/api/finance/income', $payload)->assertOk();
        $this->assertDatabaseCount('receitas', 1);
        $this->getJson('/api/finance/income?month=2026-10&source=avulso')->assertOk()
            ->assertJsonCount(1, 'entries')->assertJsonPath('totalValue', 75.5)
            ->assertJsonPath('entries.0.source', 'avulso')->assertJsonPath('entries.0.title', 'Aula avulsa');
        $this->getJson('/api/finance?month=2026-10')->assertOk()->assertJsonPath('summary.received', 75.5)
            ->assertJsonPath('summary.manual', 75.5)->assertJsonPath('summary.expected', 75.5)->assertJsonPath('summary.open', 0);
        $this->postJson('/api/finance/income', [...$payload, 'date' => '2026-10-30'])->assertUnprocessable();
        $this->postJson('/api/finance/income', [...$payload, 'value' => -5])->assertUnprocessable();
        $this->actingAs($other)->getJson('/api/finance/income?month=2026-10')->assertOk()->assertJsonCount(0, 'entries');
        $this->postJson('/api/finance/income', $payload)->assertNotFound();
        $this->assertDatabaseCount('receitas', 1);
    }

    public function test_income_lists_only_received_entries_with_correct_sources_and_payment_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29));
        [$user, , $student] = $this->account('Entradas');
        [, , $other] = $this->account('Entradas isoladas');
        foreach ([['boleto', 'mensalidade', 'Boleto'], ['wellhub', 'wellhub', 'Transferência'], ['totalpass', 'totalpass', 'Transferência']] as [$source, $origin, $method]) {
            $charge = $this->charge($student, '2026-09-05', 100, ['status' => 'pago', 'origem' => $origin]);
            $charge->pagamento()->create(['valor' => 100, 'metodo' => $method, 'pago_em' => '2026-10-10']);
            $this->actingAs($user)->getJson('/api/finance/income?month=2026-10&source='.$source)->assertOk()
                ->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.source', $source)
                ->assertJsonPath('entries.0.date', '2026-10-10');
        }
        $this->charge($student, '2026-10-05', 200);
        $this->charge($other, '2026-10-05', 999, ['status' => 'pago', 'pago_em' => '2026-10-05']);
        $this->getJson('/api/finance/income?month=2026-10')->assertOk()->assertJsonCount(3, 'entries')->assertJsonPath('totalValue', 300);
        $this->getJson('/api/finance/income?month=2026-09')->assertJsonCount(0, 'entries');
    }

    public function test_month_buckets_chart_and_paid_list_reconcile_with_real_recurring_charges(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 29));
        [$user, $org, $student] = $this->account('Conciliacao');
        $this->charge($student, '2026-10-05', 100);
        $this->charge($student, '2026-10-29', 40);
        $this->charge($student, '2026-10-31', 60);
        $this->charge($student, '2026-10-01', 999, ['status' => 'cancelado']);
        $old = $this->charge($student, '2026-09-05', 180, ['status' => 'pago']);
        $old->pagamento()->create(['valor' => 180, 'metodo' => 'PIX', 'pago_em' => '2026-10-20']);
        $this->charge($student, '2026-10-05', 30, ['status' => 'pago', 'pago_em' => '2026-10-15']);
        // A stale paid_at on the charge must not override the authoritative payment record.
        $outside = $this->charge($student, '2026-10-05', 300, ['status' => 'pago', 'pago_em' => '2026-10-15']);
        $outside->pagamento()->create(['valor' => 300, 'metodo' => 'PIX', 'pago_em' => '2026-09-15']);
        \App\Models\Receita::create(['organizacao_id' => $org->id, 'valor' => 50, 'competencia' => '2026-10-20', 'descricao' => 'Extra']);
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 70, 'ciclo' => 'mensal', 'ativo' => true]);
        Assinatura::create(['organizacao_id' => $org->id, 'aluno_id' => $student->id, 'plano_id' => $plan->id,
            'status' => 'ativa', 'inicio_em' => '2026-10-01', 'proximo_vencimento' => '2026-10-30', 'auto_renovacao' => false]);
        $response = $this->actingAs($user)->getJson('/api/finance?month=2026-10&status=previsto')->assertOk()
            ->assertJsonPath('summary.received', 260)->assertJsonPath('summary.open', 170)
            ->assertJsonPath('summary.overdue', 100)->assertJsonPath('summary.expected', 530)
            ->assertJsonPath('listTotal', 530)->assertJsonPath('dashboard.daily.19.paid', 230)
            ->assertJsonPath('dashboard.daily.4.overdue', 100)->assertJsonPath('dashboard.daily.28.upcoming', 40);
        $this->assertEquals(260, array_sum(array_column($response->json('dashboard.daily'), 'paid')));
        $this->assertEquals(270, array_sum(array_column($response->json('dashboard.daily'), 'pending')));
        $this->getJson('/api/finance?month=2026-10&status=pago')->assertOk()->assertJsonPath('listTotal', 260)->assertJsonCount(3, 'charges');
        $this->getJson('/api/finance?month=2026-10&status=aberto')->assertOk()->assertJsonPath('listTotal', 170)->assertJsonCount(3, 'charges');
        $this->getJson('/api/finance?month=2026-10&status=atrasado')->assertOk()->assertJsonPath('listTotal', 100)->assertJsonCount(1, 'charges');
        $this->getJson('/api/finance/income?month=2026-10')->assertOk()->assertJsonPath('totalValue', 260)->assertJsonCount(3, 'entries');
    }
}
