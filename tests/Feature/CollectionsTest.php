<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Mensagem;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\User;
use App\Services\BillingService;
use App\Services\RecurringBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CollectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 21)->startOfDay());
        Http::preventStrayRequests();
    }

    private function account(string $name = 'Studio'): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => $name, 'slug' => $name, 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Ana '.$name, 'telefone' => '71999990000', 'status' => 'ativo']);
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 150, 'ciclo' => 'mensal', 'ativo' => true]);
        $subscription = app(BillingService::class)->createSubscriptionForStudent($student, $plan, '2026-07-01', '2026-07-10');

        return [$user, $org, $student, $subscription];
    }

    public function test_unpaid_months_are_generated_independently_and_grouped_once_per_student(): void
    {
        [$user, $org, $student] = $this->account();
        $this->account('Other');
        $this->actingAs($user)->getJson('/api/collections')->assertOk()
            ->assertJsonPath('summary.overdue', 450)->assertJsonPath('summary.students', 1)
            ->assertJsonCount(1, 'students')->assertJsonCount(3, 'students.0.installments');
        $this->getJson('/api/collections')->assertOk();
        $this->assertSame(3, $student->cobrancas()->count());
        $this->assertSame(0, Mensagem::count());
        Http::assertNothingSent();
    }

    public function test_legacy_recurrence_is_previewed_and_requires_an_unchanged_quote(): void
    {
        [$user, , $student, $subscription] = $this->account();
        $subscription->update(['recorrencia_inicio' => null]);
        $this->actingAs($user)->getJson('/api/collections')->assertJsonPath('summary.review', 1)->assertJsonPath('summary.overdue', 150);
        $preview = $this->postJson("/api/collections/{$student->id}/recurrence", [])->assertOk()->assertJsonCount(2, 'items');
        $this->assertSame(1, $student->cobrancas()->count());
        $this->postJson("/api/collections/{$student->id}/recurrence", ['confirm' => true, 'quote' => 'invalid'])->assertConflict();
        $this->postJson("/api/collections/{$student->id}/recurrence", ['start' => $preview->json('start'), 'confirm' => true, 'quote' => $preview->json('quote')])->assertOk();
        $this->getJson('/api/collections')->assertJsonPath('summary.review', 0)->assertJsonPath('summary.overdue', 450);
    }

    public function test_recurrence_respects_pause_end_cycle_and_month_end_anchor(): void
    {
        [, $org, $student, $subscription] = $this->account();
        $subscription->update(['recorrencia_inicio' => '2026-07-31', 'pausa_em' => '2026-09-01']);
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertDatabaseHas('cobrancas', ['assinatura_id' => $subscription->id, 'competencia' => '08/2026', 'vencimento' => '2026-08-31 00:00:00']);
        $this->assertSame(2, $student->cobrancas()->count());
        $subscription->update(['pausa_em' => null, 'encerramento_em' => '2026-09-01']);
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertSame(2, $student->cobrancas()->count());
        $subscription->update(['encerramento_em' => null]);
        $subscription->plano->update(['ciclo' => 'trimestral']);
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertSame(2, $student->cobrancas()->count());
    }

    public function test_grouped_message_marks_all_selected_contacts_without_changing_payment_status(): void
    {
        [$user, $org, $student] = $this->account();
        [$otherUser, , $otherStudent] = $this->account('Other');
        app(RecurringBillingService::class)->sync($org->id);
        $ids = $student->cobrancas()->pluck('id')->all();
        $this->actingAs($otherUser)->postJson("/api/collections/{$student->id}/message", ['ids' => $ids])->assertNotFound();
        $this->actingAs($user)->postJson("/api/collections/{$student->id}/message", ['ids' => [$otherStudent->cobrancas()->first()->id]])->assertNotFound();
        $response = $this->postJson("/api/collections/{$student->id}/message", ['ids' => $ids])->assertOk();
        $id = $response->json('message.id');
        $this->assertStringContainsString('450,00', $response->json('message.content'));
        $this->assertStringContainsString('07/2026', $response->json('message.content'));
        $this->postJson("/api/messages/$id/sent")->assertOk();
        $this->postJson("/api/messages/$id/sent")->assertOk();
        foreach ($student->cobrancas as $charge) {
            $this->assertNotNull($charge->enviado_em);
            $this->assertNotSame('pago', $charge->status);
            $this->assertSame(1, $charge->eventos()->where('tipo', 'mensagem_manual_enviada')->count());
        }
        Http::assertNothingSent();
    }

    public function test_selected_payments_preserve_other_debts_and_split_monthly_receipts(): void
    {
        [$user, $org, $student] = $this->account();
        app(RecurringBillingService::class)->sync($org->id);
        $july = $student->cobrancas()->where('competencia', '07/2026')->first();
        $this->actingAs($user)->postJson("/api/collections/{$student->id}/pay", ['ids' => [$july->id], 'date' => '2026-09-20', 'method' => 'PIX'])->assertOk();
        $this->postJson("/api/collections/{$student->id}/pay", ['ids' => [$july->id], 'date' => '2026-09-20', 'method' => 'PIX'])->assertOk();
        $this->assertSame(1, $july->pagamento()->count());
        $this->assertSame(3, $student->cobrancas()->count());
        $this->getJson('/api/collections')->assertJsonPath('summary.overdue', 300);
        $this->getJson('/api/finance?month=2026-09')->assertJsonPath('summary.received', 150)->assertJsonPath('summary.recovered', 150)->assertJsonPath('summary.current', 0);
        $this->getJson("/api/students/{$student->id}")->assertJsonPath('student.financial.openAmount', 300);
        $this->postJson("/api/collections/{$student->id}/pay", ['ids' => [$july->id], 'date' => '2026-09-25', 'method' => 'PIX'])->assertUnprocessable();
    }

    public function test_payment_batch_is_atomic_and_cannot_include_another_students_charge(): void
    {
        [$user, , $student] = $this->account();
        [, , $other] = $this->account('Other');
        $charge = $student->cobrancas()->first();
        $this->actingAs($user)->postJson("/api/collections/{$student->id}/pay", [
            'ids' => [$charge->id, $other->cobrancas()->first()->id], 'date' => '2026-09-20', 'method' => 'PIX',
        ])->assertNotFound();
        $this->assertDatabaseCount('pagamentos', 0);
        $this->assertNotSame('pago', $charge->fresh()->status);
    }

    public function test_recurring_anchor_does_not_drift_after_february_or_recreate_cancelled_charges(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 15));
        [, $org, $student, $subscription] = $this->account();
        $subscription->update(['inicio_em' => '2026-01-01', 'recorrencia_inicio' => '2026-01-31']);
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertDatabaseHas('cobrancas', ['assinatura_id' => $subscription->id, 'competencia' => '02/2026', 'vencimento' => '2026-02-28 00:00:00']);
        $march = $student->cobrancas()->where('competencia', '03/2026')->firstOrFail();
        $this->assertSame('2026-03-31', $march->vencimento->toDateString());
        $march->update(['status' => 'cancelado']);
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertSame('cancelado', $march->fresh()->status);
    }

    public function test_upcoming_includes_the_next_month_and_recurrence_stops_for_paused_students(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 28));
        [$user, $org, $student, $subscription] = $this->account();
        $subscription->update(['recorrencia_inicio' => '2026-07-03']);
        $this->actingAs($user)->getJson('/api/collections?filter=upcoming')->assertOk()->assertJsonPath('summary.upcoming', 150);
        $student->update(['status' => 'pausado']);
        $this->travelTo(now()->setDate(2026, 11, 15));
        app(RecurringBillingService::class)->sync($org->id);
        $this->assertDatabaseMissing('cobrancas', ['assinatura_id' => $subscription->id, 'competencia' => '11/2026']);
    }
}
