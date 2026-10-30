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

    public function test_manager_can_read_and_edit_owner_phone_in_team_row(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario');
        $organization->update(['telefone_contato' => '71996651964']);
        $organization->usuarios()->updateExistingPivot($owner->id, ['telefone' => '71996651964']);
        $other = Organizacao::create(['nome_fantasia' => 'Outro', 'slug' => 'outro', 'tipo' => 'studio', 'ativa' => true, 'telefone_contato' => '11999998888']);
        $this->actingAs($owner)->getJson('/api/organization/users')->assertOk()
            ->assertJsonPath('users.0.phone', '71996651964')->assertJsonPath('canManage', true);
        $this->patchJson('/api/organization/users/'.$owner->id, ['phone' => '(71) 99988-7766'])->assertOk()
            ->assertJsonPath('user.phone', '71999887766');
        $this->assertSame('71999887766', $organization->fresh()->telefone_contato);
        $this->assertSame('11999998888', $other->fresh()->telefone_contato);
        $this->getJson('/api/me')->assertJsonPath('organization.phone', '71999887766');
        $this->patchJson('/api/organization/users/'.$owner->id, ['phone' => 'abc71999887766'])->assertUnprocessable();
        $this->patchJson('/api/organization/users/'.$owner->id, ['phone' => '123'])->assertUnprocessable();
        $this->patchJson('/api/organization/users/'.$owner->id, ['phone' => null])->assertOk();
        $this->assertNull($organization->fresh()->telefone_contato);
    }

    public function test_professor_cannot_change_manager_phone(): void
    {
        [$teacher, $organization] = $this->organizationWithUser('professor');
        $this->actingAs($teacher)->getJson('/api/organization/users')->assertJsonPath('canManage', false);
        $this->patchJson('/api/organization/users/'.$teacher->id, ['phone' => '71996651964'])->assertForbidden();
        $this->assertNull($organization->fresh()->telefone_contato);
    }

    public function test_edit_and_delete_are_scoped_and_preserve_other_memberships(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario');
        $member = User::factory()->create();
        $organization->usuarios()->attach($member, ['papel' => 'professor', 'status' => 'ativo']);
        $this->actingAs($owner)->patchJson('/api/organization/users/'.$member->id,
            ['name' => 'Nome atualizado', 'email' => 'atualizado@example.com', 'phone' => '(11) 99999-8888'])->assertOk()
            ->assertJsonPath('user.name', 'Nome atualizado')->assertJsonPath('user.phone', '11999998888');
        $other = Organizacao::create(['nome_fantasia' => 'Outro', 'slug' => 'outro', 'tipo' => 'studio', 'ativa' => true]);
        $other->usuarios()->attach($member, ['papel' => 'administrador', 'status' => 'ativo', 'telefone' => '21999998888']);
        $this->patchJson('/api/organization/users/'.$member->id, ['phone' => '71999998888'])->assertOk();
        $this->assertSame('21999998888', $other->usuarios()->findOrFail($member->id)->pivot->telefone);
        $this->patchJson('/api/organization/users/'.$member->id, ['name' => 'Outra identidade'])->assertUnprocessable();
        $this->deleteJson('/api/organization/users/'.$owner->id)->assertUnprocessable();
        $outsider = User::factory()->create();
        $this->deleteJson('/api/organization/users/'.$outsider->id)->assertNotFound();
        $this->deleteJson('/api/organization/users/'.$member->id)->assertOk();
        $this->assertFalse($organization->usuarios()->whereKey($member->id)->exists());
        $this->assertTrue($other->usuarios()->whereKey($member->id)->exists());
        $this->assertDatabaseHas('users', ['id' => $member->id]);
        $this->actingAs($member)->deleteJson('/api/organization/users/'.$member->id)->assertUnprocessable();
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
