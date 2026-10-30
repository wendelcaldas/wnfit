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

    public function test_manager_can_read_signup_phone_and_update_only_their_organization(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario');
        $organization->update(['telefone_contato' => '71996651964']);
        $other = Organizacao::create(['nome_fantasia' => 'Outro', 'slug' => 'outro', 'tipo' => 'studio', 'ativa' => true, 'telefone_contato' => '11999998888']);
        $this->actingAs($owner)->getJson('/api/organization/users')->assertOk()
            ->assertJsonPath('contact.phone', '71996651964')->assertJsonPath('canManageContact', true);
        $this->patchJson('/api/organization/contact', ['phone' => '(71) 99988-7766'])->assertOk()
            ->assertJsonPath('contact.phone', '71999887766');
        $this->assertSame('71999887766', $organization->fresh()->telefone_contato);
        $this->assertSame('11999998888', $other->fresh()->telefone_contato);
        $this->getJson('/api/me')->assertJsonPath('organization.phone', '71999887766');
        $this->patchJson('/api/organization/contact', ['phone' => 'abc71999887766'])->assertUnprocessable();
        $this->patchJson('/api/organization/contact', ['phone' => '123'])->assertUnprocessable();
        $this->patchJson('/api/organization/contact', ['phone' => null])->assertOk();
        $this->assertNull($organization->fresh()->telefone_contato);
    }

    public function test_professor_cannot_change_manager_phone(): void
    {
        [$teacher, $organization] = $this->organizationWithUser('professor');
        $this->actingAs($teacher)->getJson('/api/organization/users')->assertJsonPath('canManageContact', false);
        $this->patchJson('/api/organization/contact', ['phone' => '71996651964'])->assertForbidden();
        $this->assertNull($organization->fresh()->telefone_contato);
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
