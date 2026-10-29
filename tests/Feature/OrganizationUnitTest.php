<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\Unidade;
use App\Models\User;
use App\Services\OrganizationUnitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrganizationUnitTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $name, string $role = 'proprietario'): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => $name, 'slug' => $name, 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => $role, 'status' => 'ativo']);
        return [$user, $org];
    }

    public function test_options_have_one_real_default_per_organization_instead_of_fixed_examples(): void
    {
        [$user, $org] = $this->account('Unidades');
        $response = $this->actingAs($user)->getJson('/api/students/options')->assertOk()
            ->assertJsonCount(1, 'units')->assertJsonPath('units.0.nome', 'Unidade principal');
        $id = $response->json('units.0.id');
        $response->assertJsonPath('defaultUnitId', $id);
        $this->getJson('/api/students/options')->assertOk();
        $this->assertSame(1, $org->unidades()->count());
        $this->getJson('/api/organization/units')->assertJsonPath('units.0.default', true);
    }

    public function test_management_preserves_default_invariants_and_isolates_organizations(): void
    {
        [$user, $org] = $this->account('Gestao');
        [$other] = $this->account('Outra');
        $default = app(OrganizationUnitService::class)->defaultFor($org->id);
        $data = ['name' => 'Centro', 'active' => true, 'default' => false];
        $id = $this->actingAs($user)->postJson('/api/organization/units', $data)->assertCreated()->json('id');
        $this->postJson('/api/organization/units', $data)->assertUnprocessable();
        $this->putJson('/api/organization/units/'.$default->id, ['name' => $default->nome, 'active' => false, 'default' => false])->assertUnprocessable();
        $this->putJson('/api/organization/units/'.$id, [...$data, 'active' => false, 'default' => true])->assertUnprocessable();
        $this->putJson('/api/organization/units/'.$id, [...$data, 'default' => true])->assertOk();
        $this->assertFalse($default->fresh()->padrao);
        $this->assertSame(1, $org->unidades()->where('padrao', true)->count());
        $this->getJson('/api/students/options')->assertJsonPath('defaultUnitId', $id);
        $this->putJson('/api/organization/units/'.$default->id, ['name' => $default->nome, 'active' => false, 'default' => false])->assertOk();
        $this->getJson('/api/students/options')->assertJsonCount(1, 'units');
        $this->actingAs($other)->putJson('/api/organization/units/'.$id, $data)->assertNotFound();
        $this->getJson('/api/organization/units')->assertJsonCount(1, 'units')->assertJsonPath('units.0.name', 'Unidade principal');
        $this->postJson('/api/organization/units', $data)->assertCreated();
    }

    public function test_teachers_can_read_but_only_managers_can_configure_units(): void
    {
        [$user] = $this->account('Equipe', 'professor');
        $this->actingAs($user)->getJson('/api/organization/units')->assertOk()->assertJsonPath('canManage', false);
        $this->postJson('/api/organization/units', ['name' => 'Outra', 'active' => true, 'default' => false])->assertForbidden();
    }

    public function test_enrollment_defaults_to_primary_and_rejects_inactive_or_foreign_units(): void
    {
        Http::preventStrayRequests();
        [$user, $org] = $this->account('Matricula');
        [, $other] = $this->account('Outra matricula');
        $default = app(OrganizationUnitService::class)->defaultFor($org->id);
        $foreign = app(OrganizationUnitService::class)->defaultFor($other->id);
        $inactive = Unidade::create(['organizacao_id' => $org->id, 'nome' => 'Fechada', 'ativa' => false]);
        $plan = Plano::create(['organizacao_id' => $org->id, 'nome' => 'Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal', 'ativo' => true]);
        $payload = ['nome' => 'Ana', 'telefone' => '71999990000', 'modalidade' => 'presencial', 'status' => 'ativo',
            'plan_id' => $plan->id, 'data_inicio' => '2026-10-01', 'data_vencimento' => '2026-10-30'];
        $this->actingAs($user)->postJson('/api/students', [...$payload, 'unit_id' => $foreign->id])->assertUnprocessable();
        $this->postJson('/api/students', [...$payload, 'unit_id' => $inactive->id])->assertUnprocessable();
        $this->postJson('/api/students', [...$payload, 'unidade' => 'Unidade inventada'])->assertUnprocessable();
        $response = $this->postJson('/api/students', $payload)->assertCreated()
            ->assertJsonPath('student.unitId', $default->id)->assertJsonPath('student.unit', 'Unidade principal');
        $student = Aluno::findOrFail($response->json('student.id'));
        $this->putJson('/api/organization/units/'.$default->id, ['name' => 'Studio Principal', 'active' => true, 'default' => true])->assertOk();
        $this->assertSame('Studio Principal', $student->fresh()->unidade);
        $new = Unidade::create(['organizacao_id' => $org->id, 'nome' => 'Nova', 'ativa' => true]);
        $this->putJson('/api/organization/units/'.$new->id, ['name' => 'Nova', 'active' => true, 'default' => true])->assertOk();
        $this->assertSame($default->id, $student->fresh()->unidade_id);
        $this->putJson('/api/organization/units/'.$default->id, ['name' => 'Studio Principal', 'active' => false, 'default' => false])->assertOk();
        $this->assertSame($default->id, $student->fresh()->unidade_id);
    }

    public function test_migration_preserves_existing_unit_labels_and_does_not_cross_organizations(): void
    {
        $migration = require database_path('migrations/2026_10_29_000002_create_organization_units.php');
        $migration->down();
        [, $org] = $this->account('Legado');
        [, $other] = $this->account('Legado outro');
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Ana', 'unidade' => 'Studio Centro', 'status' => 'ativo']);
        $second = Aluno::create(['organizacao_id' => $other->id, 'nome' => 'Bia', 'unidade' => 'Studio Centro', 'status' => 'ativo']);
        $blank = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Caio', 'status' => 'ativo']);
        $migration->up();
        $this->assertSame('Studio Centro', $student->fresh()->unit->nome);
        $this->assertSame('Studio Centro', $student->fresh()->unidade);
        $this->assertNotEquals($student->fresh()->unidade_id, $second->fresh()->unidade_id);
        $this->assertSame($org->id, $student->fresh()->unit->organizacao_id);
        $this->assertTrue($blank->fresh()->unit->padrao);
        $this->assertSame(3, Aluno::count());
        $this->assertSame(1, $org->unidades()->where('padrao', true)->count());
    }

    public function test_delete_transfers_students_atomically_and_preserves_their_records(): void
    {
        [$user, $org] = $this->account('Exclusao');
        [, $other] = $this->account('Outra exclusao');
        $default = app(OrganizationUnitService::class)->defaultFor($org->id);
        $foreign = app(OrganizationUnitService::class)->defaultFor($other->id);
        $unit = Unidade::create(['organizacao_id' => $org->id, 'nome' => 'Vila Madalena', 'ativa' => true]);
        $inactive = Unidade::create(['organizacao_id' => $org->id, 'nome' => 'Fechada', 'ativa' => false]);
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Ana', 'status' => 'ativo', 'unidade_id' => $unit->id, 'unidade' => $unit->nome]);
        $charge = $student->cobrancas()->create(['organizacao_id' => $org->id, 'competencia' => '10/2026', 'valor' => 120, 'status' => 'pendente', 'vencimento' => '2026-10-30']);
        $this->actingAs($user)->deleteJson('/api/organization/units/'.$unit->id)->assertUnprocessable();
        foreach ([$foreign->id, $unit->id, $inactive->id] as $destination) {
            $this->deleteJson('/api/organization/units/'.$unit->id, ['destination_id' => $destination])->assertUnprocessable();
        }
        $this->assertSame($unit->id, $student->fresh()->unidade_id);
        $this->assertDatabaseHas('unidades', ['id' => $unit->id]);
        $this->deleteJson('/api/organization/units/'.$unit->id, ['destination_id' => $default->id])->assertOk()->assertJsonPath('transferredStudents', 1);
        $this->assertDatabaseMissing('unidades', ['id' => $unit->id]);
        $this->assertSame($default->id, $student->fresh()->unidade_id);
        $this->assertSame($default->nome, $student->fresh()->unidade);
        $this->assertSame('Ana', $student->fresh()->nome);
        $this->assertDatabaseHas('cobrancas', ['id' => $charge->id, 'aluno_id' => $student->id, 'valor' => 120]);
    }

    public function test_delete_protects_default_units_and_requires_manager_of_same_organization(): void
    {
        [$user, $org] = $this->account('Permissao exclusao');
        [$other] = $this->account('Outra permissao');
        [$teacher] = $this->account('Professor exclusao', 'professor');
        $default = app(OrganizationUnitService::class)->defaultFor($org->id);
        $unit = Unidade::create(['organizacao_id' => $org->id, 'nome' => 'Vazia', 'ativa' => true]);
        $this->actingAs($other)->deleteJson('/api/organization/units/'.$unit->id)->assertNotFound();
        $this->actingAs($teacher)->deleteJson('/api/organization/units/'.$unit->id)->assertForbidden();
        $this->actingAs($user)->deleteJson('/api/organization/units/'.$default->id)->assertUnprocessable();
        $this->deleteJson('/api/organization/units/'.$unit->id)->assertOk()->assertJsonPath('transferredStudents', 0);
        $this->assertTrue($default->fresh()->padrao);
        $this->assertSame(1, $org->unidades()->count());
    }
}
