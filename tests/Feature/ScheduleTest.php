<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_and_edit_an_appointment_with_participants(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'agenda-principal');
        $student = Aluno::query()->create(['organizacao_id' => $organization->id, 'nome' => 'Julia Mendes', 'status' => 'ativo']);
        $payload = $this->payload($owner->id, [$student->id]);

        $created = $this->actingAs($owner)->postJson('/api/schedule', $payload)
            ->assertCreated()
            ->assertJsonPath('event.title', 'Aula funcional');

        $id = $created->json('event.id');
        $this->assertDatabaseHas('agendamento_aluno', ['agendamento_id' => $id, 'aluno_id' => $student->id, 'status' => 'confirmado']);

        $this->actingAs($owner)->getJson('/api/schedule?start=2026-08-03&end=2026-08-03')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('summary.participants', 1);

        $payload['title'] = 'Aula funcional atualizada';
        $payload['participant_ids'] = [];
        $this->actingAs($owner)->putJson("/api/schedule/{$id}", $payload)
            ->assertOk()
            ->assertJsonPath('event.title', 'Aula funcional atualizada')
            ->assertJsonPath('event.participantCount', 0);
        $this->assertDatabaseMissing('agendamento_aluno', ['agendamento_id' => $id, 'aluno_id' => $student->id]);
    }

    public function test_same_instructor_cannot_receive_overlapping_appointments(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'agenda-conflito');
        Agendamento::query()->create([
            'organizacao_id' => $organization->id, 'titulo' => 'Aula existente', 'tipo' => 'coletiva', 'status' => 'agendado',
            'inicio_em' => '2026-08-03 09:00:00', 'fim_em' => '2026-08-03 10:00:00', 'instrutor_id' => $owner->id, 'modalidade' => 'presencial',
        ]);

        $payload = $this->payload($owner->id);
        $payload['starts_at'] = '2026-08-03T09:30';
        $payload['ends_at'] = '2026-08-03T10:30';

        $this->actingAs($owner)->postJson('/api/schedule', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_recurring_appointment_creates_each_weekly_occurrence_as_an_independent_event(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'agenda-recorrente');
        $payload = $this->payload($owner->id);
        $payload['title'] = 'Boxe de terca';
        $payload['repeat_frequency'] = 'weekly';
        $payload['repeat_until'] = '2026-08-24';

        $response = $this->actingAs($owner)->postJson('/api/schedule', $payload)
            ->assertCreated()
            ->assertJsonPath('createdCount', 4)
            ->assertJsonPath('event.recurrenceFrequency', 'weekly');

        $seriesId = $response->json('event.seriesId');
        $this->assertNotNull($seriesId);
        $this->assertDatabaseCount('agendamentos', 4);
        $this->assertDatabaseHas('agendamentos', ['serie_id' => $seriesId, 'recorrencia_ate' => '2026-08-24 00:00:00']);
    }

    public function test_professor_only_sees_and_creates_appointments_in_own_agenda(): void
    {
        [$professor, $organization] = $this->organizationWithUser('professor', 'agenda-professor');
        $otherProfessor = User::factory()->create();
        $organization->usuarios()->attach($otherProfessor->id, ['papel' => 'professor', 'status' => 'ativo']);

        Agendamento::query()->create([
            'organizacao_id' => $organization->id, 'titulo' => 'Agenda de outra pessoa', 'tipo' => 'individual', 'status' => 'agendado',
            'inicio_em' => '2026-08-03 11:00:00', 'fim_em' => '2026-08-03 12:00:00', 'instrutor_id' => $otherProfessor->id, 'modalidade' => 'presencial',
        ]);

        $payload = $this->payload($otherProfessor->id);
        $created = $this->actingAs($professor)->postJson('/api/schedule', $payload)->assertCreated();
        $this->assertSame($professor->id, $created->json('event.instructorId'));

        $this->actingAs($professor)->getJson('/api/schedule?start=2026-08-03&end=2026-08-03')
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.instructorId', $professor->id);
    }

    private function payload(?int $instructorId, array $participantIds = []): array
    {
        return [
            'title' => 'Aula funcional', 'type' => 'coletiva', 'status' => 'agendado',
            'starts_at' => '2026-08-03T09:00', 'ends_at' => '2026-08-03T10:00',
            'instructor_id' => $instructorId, 'modality' => 'presencial', 'location' => 'Sala 1',
            'capacity' => 12, 'participant_ids' => $participantIds,
        ];
    }

    private function organizationWithUser(string $role, string $slug): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create([
            'nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true,
        ]);
        $organization->usuarios()->attach($user->id, ['papel' => $role, 'status' => 'ativo']);

        return [$user, $organization];
    }
}
