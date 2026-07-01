<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_professor_and_student_options_only_show_active_professors(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario');

        $response = $this->actingAs($owner)->postJson('/api/organization/users', [
            'name' => 'Ana Professora',
            'email' => 'ana@example.com',
            'password' => 'Senha123!',
            'role' => 'professor',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.role', 'professor')
            ->assertJsonPath('user.status', 'ativo');

        $professorId = $response->json('user.id');

        $this->actingAs($owner)->getJson('/api/students/options')
            ->assertOk()
            ->assertJsonPath('teachers.0', 'Ana Professora');

        $this->actingAs($owner)->patchJson("/api/organization/users/{$professorId}", ['status' => 'inativo'])
            ->assertOk();

        $this->actingAs($owner)->getJson('/api/students/options')
            ->assertOk()
            ->assertJsonCount(0, 'teachers');
    }

    public function test_professor_cannot_create_users(): void
    {
        [$professor] = $this->organizationWithUser('professor');

        $this->actingAs($professor)->postJson('/api/organization/users', [
            'name' => 'Outro Usuario',
            'email' => 'outro@example.com',
            'password' => 'Senha123!',
            'role' => 'professor',
        ])->assertForbidden();
    }

    private function organizationWithUser(string $role): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create([
            'nome_fantasia' => 'Academia Teste',
            'slug' => 'academia-teste',
            'tipo' => 'academia',
            'ativa' => true,
        ]);
        $organization->usuarios()->attach($user->id, ['papel' => $role, 'status' => 'ativo']);

        return [$user, $organization];
    }
}
