<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\Exercicio;
use App\Models\Treino;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_workout_library_is_scoped_to_the_authenticated_organization(): void
    {
        [$user, $organization] = $this->organizationWithUser('academia-a');
        [, $otherOrganization] = $this->organizationWithUser('academia-b');

        Treino::query()->create([
            'organizacao_id' => $organization->id,
            'criado_por' => $user->id,
            'nome' => 'Hipertrofia A',
            'objetivo' => 'Hipertrofia',
            'nivel' => 'intermediario',
            'status' => 'ativo',
        ]);
        Treino::query()->create([
            'organizacao_id' => $otherOrganization->id,
            'nome' => 'Treino externo',
            'objetivo' => 'Condicionamento',
            'status' => 'ativo',
        ]);

        $this->actingAs($user)->getJson('/api/workouts')
            ->assertOk()
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonCount(1, 'workouts')
            ->assertJsonPath('workouts.0.name', 'Hipertrofia A')
            ->assertJsonPath('workouts.0.author', $user->name);
    }

    public function test_workout_library_can_be_filtered(): void
    {
        [$user, $organization] = $this->organizationWithUser('academia-filtros');
        Treino::query()->create(['organizacao_id' => $organization->id, 'nome' => 'Forca A', 'objetivo' => 'Forca', 'status' => 'ativo']);
        Treino::query()->create(['organizacao_id' => $organization->id, 'nome' => 'Mobilidade', 'objetivo' => 'Mobilidade', 'status' => 'rascunho']);

        $this->actingAs($user)->getJson('/api/workouts?status=rascunho&objetivo=Mobilidade')
            ->assertOk()
            ->assertJsonCount(1, 'workouts')
            ->assertJsonPath('workouts.0.name', 'Mobilidade');
    }

    public function test_workout_builder_creates_and_updates_days_and_exercises(): void
    {
        [$user] = $this->organizationWithUser('academia-builder');
        $exercise = Exercicio::query()->create(['nome' => 'Supino reto', 'grupo_muscular' => 'Peitoral', 'equipamento' => 'Barra']);
        $payload = [
            'nome' => 'Treino ABC', 'objetivo' => 'Hipertrofia', 'nivel' => 'intermediario',
            'sessoes_semana' => 3, 'duracao_semanas' => 8, 'status' => 'rascunho', 'descricao' => 'Programa inicial',
            'days' => [[
                'name' => 'Treino A', 'focus' => 'Peito e triceps',
                'exercises' => [[
                    'exerciseId' => $exercise->id, 'name' => 'Supino reto', 'muscleGroup' => 'Peitoral', 'sets' => 4,
                    'repetitions' => '8-12', 'load' => 'Progressiva', 'restSeconds' => 90, 'notes' => 'Controlar descida',
                ]],
            ]],
        ];

        $created = $this->actingAs($user)->postJson('/api/workouts', $payload)
            ->assertCreated()
            ->assertJsonPath('workout.days.0.exercises.0.name', 'Supino reto');

        $id = $created->json('workout.id');
        $payload['status'] = 'ativo';
        $payload['days'][0]['exercises'][0]['name'] = 'Supino inclinado';

        $this->actingAs($user)->putJson("/api/workouts/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('workout.status', 'ativo')
            ->assertJsonPath('workout.days.0.exercises.0.name', 'Supino inclinado');

        $this->assertDatabaseCount('treino_dias', 1);
        $this->assertDatabaseCount('treino_exercicios', 1);
    }

    public function test_workout_builder_rejects_access_from_another_organization(): void
    {
        [$owner, $organization] = $this->organizationWithUser('academia-dona');
        [$outsider] = $this->organizationWithUser('academia-externa');
        $workout = Treino::query()->create(['organizacao_id' => $organization->id, 'criado_por' => $owner->id, 'nome' => 'Privado', 'objetivo' => 'Forca']);

        $this->actingAs($outsider)->getJson("/api/workouts/{$workout->id}")->assertNotFound();
    }

    private function organizationWithUser(string $slug): array
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
