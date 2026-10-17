<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Event;
use App\Models\Organizacao;
use App\Models\Pagamento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventBillingTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $owner = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => 'Studio', 'slug' => Str::uuid(), 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($owner, ['papel' => 'proprietario', 'status' => 'ativo']);
        $event = Event::create(['organizacao_id' => $org->id, 'name' => 'Encontro pago', 'organizer' => 'Studio', 'slug' => Str::uuid(), 'status' => 'published', 'audience' => 'open', 'location' => 'Studio', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'checkin_opens_at' => now()->subHour(), 'is_paid' => true, 'price' => 50]);

        return [$owner, $org, $event];
    }

    private function payload(Event $event): array
    {
        return ['name' => $event->name, 'organizer' => $event->organizer, 'location' => $event->location, 'starts_at' => $event->starts_at->toIso8601String(), 'ends_at' => $event->ends_at->toIso8601String(), 'checkin_opens_at' => $event->checkin_opens_at->toIso8601String(), 'status' => $event->status, 'is_paid' => true, 'price' => 50];
    }

    public function test_public_paid_registration_generates_only_one_off_charge(): void
    {
        [$owner,$org,$event] = $this->context();
        $url = '/api/public/events/'.$event->slug;
        $this->postJson($url.'/register', ['name' => 'Visitante', 'phone' => '71999991234', 'privacy' => true])->assertCreated()->assertJsonPath('participant.payment.value', 50);
        $charge = Cobranca::first();
        $this->assertSame('evento', $charge->origem);
        $this->assertNull($charge->assinatura_id);
        $this->assertDatabaseCount('assinaturas', 0);
        $this->assertDatabaseCount('planos', 0);
        $registration = $event->registrations()->first();
        $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'student_id' => $registration->aluno_id])->assertOk();
        $this->assertDatabaseCount('cobrancas', 1);
        $this->getJson('/api/finance?month='.today()->format('Y-m'))->assertOk()->assertJsonPath('charges.0.plan', 'Evento: Encontro pago')->assertJsonPath('summary.open', 50);
    }

    public function test_payment_is_idempotent_and_teacher_cannot_pay_through_other_finance_endpoints(): void
    {
        [$owner,$org,$event] = $this->context();
        $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'name' => 'Visitante'])->assertOk();
        $registration = $event->registrations()->first();
        $charge = Cobranca::first();
        $teacher = User::factory()->create();
        $org->usuarios()->attach($teacher, ['papel' => 'professor', 'status' => 'ativo']);
        $url = "/api/events/{$event->id}/registrations/{$registration->id}/payment";
        $data = ['method' => 'Crédito', 'paid_at' => today()->toDateString()];
        $this->actingAs($teacher)->postJson($url, $data)->assertForbidden();
        $this->postJson('/api/charges/'.$charge->id.'/pay', ['method' => 'PIX'])->assertForbidden();
        $this->postJson('/api/collections/'.$registration->aluno_id.'/pay', ['ids' => [$charge->id], 'method' => 'PIX', 'date' => today()->toDateString()])->assertForbidden();
        $this->actingAs($owner)->postJson($url, $data)->assertOk();
        $this->postJson($url, $data)->assertOk();
        $this->assertDatabaseCount('pagamentos', 1);
        $this->assertSame('Crédito', Pagamento::first()->metodo);
        $this->getJson('/api/events')->assertJsonPath('0.paid_total', 50);
    }

    public function test_price_is_fixed_and_cancellation_preserves_received_money(): void
    {
        [$owner,$org,$event] = $this->context();
        $this->actingAs($owner);
        $this->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'name' => 'Pessoa Paga', 'paid' => true, 'method' => 'PIX', 'paid_at' => today()->toDateString()])->assertOk();
        $this->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'name' => 'Pessoa Pendente'])->assertOk();
        $this->patchJson('/api/events/'.$event->id, [...$this->payload($event), 'price' => 60])->assertUnprocessable();
        $this->patchJson('/api/events/'.$event->id, [...$this->payload($event), 'is_paid' => false])->assertUnprocessable();
        $this->patchJson('/api/events/'.$event->id, [...$this->payload($event), 'status' => 'cancelled'])->assertOk();
        $this->assertDatabaseHas('cobrancas', ['status' => 'pago', 'refund_review_required' => true]);
        $this->assertDatabaseHas('cobrancas', ['status' => 'cancelado']);
        $this->assertSame(50.0, (float) Pagamento::sum('valor'));
        $this->patchJson('/api/events/'.$event->id, [...$this->payload($event), 'status' => 'cancelled'])->assertOk();
        $this->assertDatabaseCount('pagamentos', 1);
    }

    public function test_immediate_payment_validation_rolls_back_registration_and_free_event_does_not_charge(): void
    {
        [$owner,$org,$event] = $this->context();
        $this->actingAs($owner);
        $this->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'name' => 'Pessoa', 'paid' => true])->assertUnprocessable();
        $this->assertDatabaseCount('alunos', 0);
        $event->update(['is_paid' => false, 'price' => 0]);
        $this->postJson("/api/events/{$event->id}/registrations", ['request_id' => Str::uuid(), 'name' => 'Pessoa'])->assertOk();
        $this->assertDatabaseCount('cobrancas', 0);
    }
}
