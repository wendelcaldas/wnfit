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

    public function test_dashboard_calendar_returns_complete_month_and_only_organization_active_events(): void
    {
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'calendar');
        [, $other] = $this->organizationWithUser('proprietario', 'other-calendar');
        $make = fn ($org, $start, $end, $status = 'agendado') => Agendamento::create([
            'organizacao_id' => $org->id, 'titulo' => 'Aula', 'inicio_em' => $start,
            'fim_em' => $end, 'status' => $status, 'tipo' => 'coletiva', 'modalidade' => 'presencial',
        ]);
        $make($organization, '2026-09-30 23:00', '2026-10-01 01:00');
        foreach ([1, 10, 20, 25, 30, 31] as $day) {
            $make($organization, "2026-10-{$day} 09:00", "2026-10-{$day} 10:00");
        }
        $make($organization, '2026-11-01 09:00', '2026-11-01 10:00');
        $make($organization, '2026-10-03 09:00', '2026-10-03 10:00', 'cancelado');
        $make($other, '2026-10-03 09:00', '2026-10-03 10:00');
        $this->actingAs($owner)->getJson('/api/dashboard/calendar?month=2026-10')
            ->assertOk()->assertJsonPath('month', '2026-10')->assertJsonCount(7, 'events')
            ->assertJsonPath('events.0.date', '2026-09-30')->assertJsonPath('events.6.date', '2026-10-31');
        $this->getJson('/api/dashboard/calendar?month=2026-11')->assertOk()->assertJsonCount(1, 'events');
        $this->getJson('/api/dashboard/calendar?month=2026-12')->assertOk()->assertJsonCount(0, 'events');
        $this->getJson('/api/dashboard/calendar?month=2026-99')->assertUnprocessable();
    }

    public function test_dashboard_counts_confirmed_reservations_for_today_without_financial_data(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-26 12:00:00'));
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'operations');
        [, $other] = $this->organizationWithUser('proprietario', 'other-operations');
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno ativo', 'status' => 'ativo']);
        $inactive = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno inativo', 'status' => 'inativo']);
        $make = function ($org, $date, $status = 'agendado') use ($student) {
            $event = Agendamento::create(['organizacao_id' => $org->id, 'titulo' => 'Aula', 'inicio_em' => "$date 14:00", 'fim_em' => "$date 15:00", 'status' => $status]);
            $event->alunos()->attach($student->id, ['status' => 'confirmado']);
            return $event;
        };
        $first = $make($organization, '2026-10-26');
        $first->alunos()->attach($inactive->id, ['status' => 'cancelado']);
        $make($organization, '2026-10-26');
        $make($organization, '2026-10-26', 'cancelado');
        $make($organization, '2026-10-27');
        $make($other, '2026-10-26');
        $response = $this->actingAs($owner)->getJson('/api/dashboard')->assertOk()
            ->assertJsonCount(4, 'stats')->assertJsonPath('stats.0.value', '1')
            ->assertJsonPath('stats.1.value', '0')->assertJsonPath('stats.2.value', '2')
            ->assertJsonPath('stats.3.type', 'reservations')->assertJsonPath('stats.3.value', '2')
            ->assertJsonMissingPath('incomeBars');
        $this->assertStringNotContainsString('Faturamento', $response->getContent());
        $this->assertStringNotContainsString('R$', $response->getContent());
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
