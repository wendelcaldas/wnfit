<?php

namespace Tests\Feature;

use App\Models\Exercicio;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_combines_global_and_organization_exercises_without_leaking_custom_entries(): void
    {
        [$user, $organization] = $this->organizationWithUser('academia-a');
        [, $otherOrganization] = $this->organizationWithUser('academia-b');
        Exercicio::query()->create(['nome' => 'Agachamento livre', 'grupo_muscular' => 'Quadriceps']);
        Exercicio::query()->create(['organizacao_id' => $organization->id, 'nome' => 'Variacao interna', 'grupo_muscular' => 'Core']);
        Exercicio::query()->create(['organizacao_id' => $otherOrganization->id, 'nome' => 'Exercicio privado', 'grupo_muscular' => 'Costas']);

        $this->actingAs($user)->getJson('/api/exercises')
            ->assertOk()->assertJsonCount(2, 'exercises')
            ->assertJsonPath('exercises.0.name', 'Agachamento livre')
            ->assertJsonPath('exercises.1.name', 'Variacao interna');
    }

    public function test_user_can_create_custom_exercise_for_organization(): void
    {
        [$user, $organization] = $this->organizationWithUser('academia-custom');
        $this->actingAs($user)->postJson('/api/exercises', [
            'name' => 'Remada personalizada', 'muscleGroup' => 'Costas', 'secondaryMuscle' => 'Biceps',
            'equipment' => 'Elastico', 'category' => 'funcional', 'level' => 'todos',
        ])->assertCreated()->assertJsonPath('exercise.custom', true);

        $this->assertDatabaseHas('exercicios', ['organizacao_id' => $organization->id, 'nome' => 'Remada personalizada']);
    }

    private function organizationWithUser(string $slug): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        return [$user, $organization];
    }
}
