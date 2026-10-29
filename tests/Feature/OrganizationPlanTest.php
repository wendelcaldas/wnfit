<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizationPlanTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name, string $role = 'proprietario'): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => $name, 'slug' => $name, 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => $role, 'status' => 'ativo']);

        return [$user, $org];
    }

    public function test_plan_management_is_scoped_and_names_are_unique_per_organization(): void
    {
        [$user, $org] = $this->account('Planos');
        [$other] = $this->account('Outro');
        $data = ['name' => 'Funcional', 'value' => 180, 'cycle' => 'mensal', 'active' => true];
        $response = $this->actingAs($user)->postJson('/api/organization/plans', $data)->assertCreated();
        $id = $response->json('id');
        $this->getJson('/api/organization/plans')->assertOk()->assertJsonPath('canManage', true)
            ->assertJsonCount(1, 'plans')->assertJsonPath('plans.0.value', 180);
        $this->postJson('/api/organization/plans', $data)->assertUnprocessable();
        $this->putJson('/api/organization/plans/'.$id, [...$data, 'value' => -1])->assertUnprocessable();
        $this->putJson('/api/organization/plans/'.$id, [...$data, 'cycle' => 'invalido'])->assertUnprocessable();
        $this->actingAs($other)->getJson('/api/organization/plans')->assertJsonCount(0, 'plans');
        $this->putJson('/api/organization/plans/'.$id, $data)->assertNotFound();
        $this->postJson('/api/organization/plans', $data)->assertCreated();
        $this->assertDatabaseHas('planos', ['id' => $id, 'organizacao_id' => $org->id, 'valor_mensal' => 180]);
    }

    public function test_staff_without_management_role_can_read_but_cannot_edit_plans(): void
    {
        [$user, $org] = $this->account('Professor', 'professor');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        $this->actingAs($user)->getJson('/api/organization/plans')->assertOk()->assertJsonPath('canManage', false);
        $data = ['name' => 'Novo', 'value' => 150, 'cycle' => 'mensal', 'active' => true];
        $this->postJson('/api/organization/plans', $data)->assertForbidden();
        $this->putJson('/api/organization/plans/'.$plan->id, $data)->assertForbidden();
    }

    public function test_price_changes_preserve_issued_charges_and_deactivation_preserves_subscriptions(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 15));
        [$user, $org] = $this->account('Precos');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Ana', 'status' => 'ativo', 'plano' => 'Mensal']);
        $subscription = app(BillingService::class)->createSubscriptionForStudent($student, $plan, '2026-10-01', '2026-10-20');
        $data = ['name' => 'Funcional', 'value' => 160, 'cycle' => 'mensal', 'active' => false];
        $this->actingAs($user)->putJson('/api/organization/plans/'.$plan->id, $data)->assertOk();
        $this->assertEquals(120, $subscription->cobrancas()->first()->valor);
        $this->assertSame('Funcional', $student->fresh()->plano);
        $this->assertSame('ativa', $subscription->fresh()->status);
        $next = app(BillingService::class)->generateCharge($subscription->fresh('plano'), '2026-11-20');
        $this->assertEquals(160, $next->valor);
        $this->getJson('/api/students/options')->assertOk()->assertJsonCount(0, 'plans');
        $this->putJson('/api/organization/plans/'.$plan->id, [...$data, 'cycle' => 'anual'])->assertUnprocessable();
    }

    public function test_student_enrollment_uses_selected_active_plan_price_and_cycle(): void
    {
        Http::preventStrayRequests();
        [$user, $org] = $this->account('Matriculas');
        [, $other] = $this->account('Outra matricula');
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Trimestral', 'valor_mensal' => 450, 'ciclo' => 'trimestral', 'ativo' => true]);
        $foreign = Plano::create(['organizacao_id' => $other->id, 'nome' => 'Outro', 'valor_mensal' => 10, 'ciclo' => 'mensal', 'ativo' => true]);
        $payload = ['nome' => 'Ana Teste', 'telefone' => '71999990000', 'modalidade' => 'presencial', 'status' => 'ativo',
            'plan_id' => $plan->id, 'valor_mensal' => 1, 'data_inicio' => '2026-10-01', 'data_vencimento' => '2026-10-10'];
        $this->actingAs($user)->postJson('/api/students', [...$payload, 'plan_id' => $foreign->id])->assertUnprocessable();
        $plan->update(['ativo' => false]);
        $this->postJson('/api/students', $payload)->assertUnprocessable();
        $plan->update(['ativo' => true]);
        $this->postJson('/api/students', $payload)->assertCreated()->assertJsonPath('student.financial.monthlyValue', 450)
            ->assertJsonPath('student.financial.subscription.cycle', 'trimestral');
        $this->assertDatabaseHas('cobrancas', ['organizacao_id' => $org->id, 'valor' => 450]);
    }
}
