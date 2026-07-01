<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_health_can_be_completed_after_registration(): void
    {
        [$user, $organization] = $this->organizationWithUser();
        $student = Aluno::query()->create([
            'organizacao_id' => $organization->id,
            'nome' => 'Aluno Rapido',
            'telefone' => '(71) 99999-0000',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/students/{$student->id}", [
            'email' => 'aluno@example.com',
            'data_nascimento' => '1995-05-20',
            'contato_emergencia' => 'Maria',
            'telefone_emergencia' => '(71) 98888-0000',
            'objetivo' => 'Ganho de massa',
            'peso' => 78.5,
            'altura' => 180,
            'restricoes_medicas' => 'Nenhuma restricao conhecida',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('student.profile.emergencyContact', 'Maria')
            ->assertJsonPath('student.health.goal', 'Ganho de massa');

        $this->assertGreaterThan(50, $response->json('student.profileCompletion'));
        $this->assertDatabaseHas('alunos', [
            'id' => $student->id,
            'contato_emergencia' => 'Maria',
            'restricoes_medicas' => 'Nenhuma restricao conhecida',
        ]);
    }

    public function test_student_from_another_organization_cannot_be_updated(): void
    {
        [$user] = $this->organizationWithUser('academia-a');
        [, $otherOrganization] = $this->organizationWithUser('academia-b');
        $student = Aluno::query()->create([
            'organizacao_id' => $otherOrganization->id,
            'nome' => 'Aluno Externo',
            'telefone' => '(71) 90000-0000',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
        ]);

        $this->actingAs($user)->patchJson("/api/students/{$student->id}", ['nome' => 'Nome Alterado'])
            ->assertNotFound();
    }

    private function organizationWithUser(string $slug = 'academia-teste'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create([
            'nome_fantasia' => $slug,
            'slug' => $slug,
            'tipo' => 'academia',
            'ativa' => true,
        ]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$user, $organization];
    }
}
