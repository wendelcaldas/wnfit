<?php

namespace Tests\Feature;

use App\Models\Exercicio;
use App\Models\Organizacao;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
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

    public function test_seed_expands_catalog_idempotently_without_overwriting_reviewed_or_custom_exercises(): void
    {
        [, $organization] = $this->organizationWithUser('catalog-review');
        $reviewed = Exercicio::create(['nome' => 'Agachamento livre', 'grupo_muscular' => 'Quadriceps', 'ativo' => false,
            'instrucoes' => 'Orientação revisada', 'imagem_url' => '/approved/squat.webp']);
        $old = Exercicio::create(['nome' => 'Supino reto', 'grupo_muscular' => 'Peitoral']);
        $custom = Exercicio::create(['organizacao_id' => $organization->id, 'nome' => 'Supino reto', 'grupo_muscular' => 'Peitoral', 'instrucoes' => 'Orientação da academia']);

        $this->seed(ExerciseSeeder::class);
        $this->seed(ExerciseSeeder::class);

        $this->assertSame(80, Exercicio::whereNull('organizacao_id')->count());
        $this->assertDatabaseCount('exercicios', 81);
        $this->assertFalse($reviewed->refresh()->ativo);
        $this->assertSame('Orientação revisada', $reviewed->instrucoes);
        $this->assertSame('/approved/squat.webp', $reviewed->imagem_url);
        $this->assertNotEmpty($old->refresh()->instrucoes);
        $this->assertSame('Orientação da academia', $custom->refresh()->instrucoes);
        $this->assertSame(0, Exercicio::whereNull('organizacao_id')->whereNull('instrucoes')->count());
    }

    public function test_expanded_catalog_can_be_paginated_and_filtered_by_equipment(): void
    {
        [$user] = $this->organizationWithUser('catalog-expanded');
        $this->seed(ExerciseSeeder::class);

        $this->actingAs($user)->getJson('/api/exercises')->assertOk()
            ->assertJsonPath('pagination.total', 80)->assertJsonCount(40, 'exercises');
        $this->getJson('/api/exercises?page=2')->assertOk()->assertJsonCount(40, 'exercises');
        $this->getJson('/api/exercises?equipment=Bicicleta')->assertOk()
            ->assertJsonCount(1, 'exercises')->assertJsonPath('exercises.0.name', 'Bicicleta ergometrica');
    }

    public function test_every_standard_exercise_has_a_local_webp_illustration_without_replacing_approved_media(): void
    {
        $this->seed(ExerciseSeeder::class);
        $manifest = json_decode(file_get_contents(resource_path('js/data/exerciseIllustrations.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(80, $manifest);
        foreach (Exercicio::whereNull('organizacao_id')->get() as $exercise) {
            $this->assertStringStartsWith('/media/exercises/v1/', $exercise->imagem_url);
            $file = public_path(ltrim($exercise->imagem_url, '/'));
            $this->assertFileExists($file);
            $this->assertSame(IMAGETYPE_WEBP, getimagesize($file)[2]);
        }
    }

    private function organizationWithUser(string $slug): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$user, $organization];
    }
}
