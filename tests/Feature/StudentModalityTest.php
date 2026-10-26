<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentModalityTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $slug = 'modalidades'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        return [$user, $organization];
    }

    private function payload(): array
    {
        return ['nome' => 'Aluno modalidade', 'telefone' => '71999990000', 'plano' => 'Mensal', 'status' => 'ativo', 'data_inicio' => today()->toDateString(), 'data_vencimento' => today()->addMonth()->toDateString()];
    }

    public function test_new_registration_requires_explicit_valid_modality_and_persists_each_choice(): void
    {
        [$owner, $organization] = $this->context();
        $this->actingAs($owner);
        foreach ([[], ['modalidade' => null], ['modalidade' => 'remoto'], ['modalidade' => ['online']]] as $invalid) {
            $this->postJson('/api/students', [...$this->payload(), ...$invalid])->assertUnprocessable()->assertJsonValidationErrors('modalidade');
        }
        $this->assertDatabaseCount('alunos', 0);
        foreach (Aluno::MODALIDADES as $value => $label) {
            $created = $this->postJson('/api/students', [...$this->payload(), 'modalidade' => $value])->assertCreated()
                ->assertJsonPath('student.modality', $value)->assertJsonPath('student.modalityLabel', $label);
            $this->assertDatabaseHas('alunos', ['id' => $created->json('student.id'), 'organizacao_id' => $organization->id, 'modalidade' => $value]);
        }
    }

    public function test_legacy_profile_can_be_saved_regularized_and_cannot_be_changed_by_another_organization(): void
    {
        [$owner, $organization] = $this->context();
        [$outsider] = $this->context('outra');
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Legado', 'status' => 'ativo']);
        $this->actingAs($owner)->getJson("/api/students/{$student->id}")->assertOk()->assertJsonPath('student.modality', null)->assertJsonPath('student.modalityLabel', 'Não informado');
        $this->patchJson("/api/students/{$student->id}", ['nome' => 'Legado atualizado'])->assertOk()->assertJsonPath('student.modality', null);
        $this->patchJson("/api/students/{$student->id}", ['modalidade' => 'hibrido'])->assertOk()->assertJsonPath('student.modality', 'hibrido');
        $this->patchJson("/api/students/{$student->id}", ['observacoes' => 'Outra alteração'])->assertOk()->assertJsonPath('student.modality', 'hibrido');
        $this->patchJson("/api/students/{$student->id}", ['modalidade' => 'ambos'])->assertUnprocessable();
        $this->actingAs($outsider)->patchJson("/api/students/{$student->id}", ['modalidade' => 'online'])->assertNotFound();
        $this->assertSame('hibrido', $student->fresh()->modalidade);
    }

    public function test_summary_is_global_exclusive_and_scoped_while_list_can_be_filtered(): void
    {
        [$owner, $organization] = $this->context();
        [, $other] = $this->context('outra');
        foreach ([null, 'presencial', 'online', 'hibrido'] as $modality) {
            Aluno::create(['organizacao_id' => $organization->id, 'nome' => $modality ?? 'Legado', 'status' => 'ativo', 'modalidade' => $modality]);
        }
        Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Pausado', 'status' => 'pausado', 'modalidade' => 'online']);
        Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Participante', 'perfil_tipo' => 'participante', 'status' => 'ativo', 'modalidade' => 'online']);
        Aluno::create(['organizacao_id' => $other->id, 'nome' => 'Outra academia', 'status' => 'ativo', 'modalidade' => 'online']);
        $response = $this->actingAs($owner)->getJson('/api/students?modalidade=hibrido&q=hibrido')->assertOk()
            ->assertJsonCount(1, 'students')->assertJsonPath('students.0.modality', 'hibrido')->assertJsonPath('summary.1.value', '4');
        $summary = $response->json('modalitySummary');
        $this->assertSame([1, 1, 1, 1], array_column($summary, 'count'));
        $this->assertSame(4, array_sum(array_column($summary, 'count')));
        $this->getJson('/api/students?modalidade=nao_informado')->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.modality', null);
        $this->getJson('/api/students?modalidade=online&status=ativo')->assertOk()->assertJsonCount(1, 'students')->assertJsonPath('students.0.name', 'online');
        $this->getJson('/api/students?modalidade=invalid')->assertUnprocessable();
    }
}
