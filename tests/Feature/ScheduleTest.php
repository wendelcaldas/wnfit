<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_schedule_reads_do_not_spend_reservation_attempts(): void
    {
        $this->organizationWithUser('proprietario', 'turmas');
        $schedule = '/api/public/schedule/turmas';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->getJson($schedule)->assertOk();
            $this->getJson($schedule.'/availability?date='.today()->toDateString())->assertOk();
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson($schedule.'/reservations', [])->assertUnprocessable();
        }

        $this->postJson($schedule.'/reservations', [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->getJson($schedule)->assertOk();
    }

    public function test_public_schedule_lists_only_published_future_appointments_with_free_spots(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(8, 0));
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'turmas');
        [, $other] = $this->organizationWithUser('proprietario', 'outras-turmas');
        $confirmed = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Nome privado', 'status' => 'ativo']);
        $cancelled = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Outra pessoa', 'status' => 'ativo']);
        $create = fn (array $fields) => Agendamento::create($fields + [
            'organizacao_id' => $organization->id, 'titulo' => 'Aula pública', 'status' => 'agendado',
            'inicio_em' => '2026-11-07 09:00', 'fim_em' => '2026-11-07 10:00',
            'publicar_vagas' => true, 'capacidade' => 2, 'observacoes' => 'Nota privada',
        ]);
        $event = $create([]);
        $event->alunos()->attach($confirmed->id);
        $event->alunos()->attach($cancelled->id, ['status' => 'cancelado']);
        $create(['titulo' => 'Sem publicação', 'publicar_vagas' => false]);
        $create(['titulo' => 'Cancelada', 'status' => 'cancelado']);
        $create(['titulo' => 'Concluída', 'status' => 'concluido']);
        $create(['titulo' => 'Sem capacidade', 'capacidade' => null]);
        $create(['titulo' => 'Outro ambiente', 'organizacao_id' => $other->id]);
        $create(['titulo' => 'Já começou', 'inicio_em' => '2026-11-07 07:00']);
        $create(['titulo' => 'Lotada', 'capacidade' => 1])->alunos()->attach($confirmed->id);
        $create(['titulo' => 'Outro dia', 'inicio_em' => '2026-11-08 09:00', 'fim_em' => '2026-11-08 10:00']);
        $create(['titulo' => 'Mesmo dia', 'inicio_em' => '2026-11-08 10:00', 'fim_em' => '2026-11-08 11:00']);
        $create(['titulo' => 'Dia lotado', 'inicio_em' => '2026-11-09 09:00', 'fim_em' => '2026-11-09 10:00', 'capacidade' => 1])->alunos()->attach($confirmed->id);
        $create(['titulo' => 'Dia privado', 'inicio_em' => '2026-11-10 09:00', 'fim_em' => '2026-11-10 10:00', 'publicar_vagas' => false]);
        $create(['titulo' => 'Fora do período', 'inicio_em' => '2027-01-07 09:00', 'fim_em' => '2027-01-07 10:00']);

        $this->getJson('/api/public/schedule/turmas')->assertOk()->assertExactJson([
            'organization' => 'turmas', 'today' => '2026-11-07', 'maxDate' => '2027-01-06',
            'availableDates' => ['2026-11-07', '2026-11-08'],
        ]);
        $availability = '/api/public/schedule/turmas/availability?date=2026-11-07';
        $this->getJson($availability)->assertOk()->assertExactJson([
            'date' => '2026-11-07',
            'events' => [['id' => $event->id, 'title' => 'Aula pública', 'date' => '2026-11-07', 'start' => '09:00', 'end' => '10:00', 'availableSpots' => 1, 'capacity' => 2]],
        ]);
        $this->getJson('/api/public/schedule/inexistente')->assertNotFound();
        $this->actingAs($owner)->getJson('/api/schedule/options')->assertJsonPath('publicScheduleUrl', '/agenda-publica/turmas');

        $this->putJson('/api/schedule/'.$event->id, [
            ...$this->payload(null, [$confirmed->id, $cancelled->id]),
            'title' => 'Aula atualizada', 'starts_at' => '2026-11-07T09:00', 'ends_at' => '2026-11-07T10:00', 'capacity' => 2,
        ])->assertOk()->assertJsonPath('event.publishSpots', true);
        $this->getJson($availability)->assertJsonPath('events.0.availableSpots', 1);
    }

    public function test_public_reservation_claims_an_existing_spot_once_and_keeps_contact_private(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(8, 0));
        [$owner, $organization] = $this->organizationWithUser('proprietario', 'turmas');
        [, $other] = $this->organizationWithUser('proprietario', 'outra-agenda');
        $event = $organization->agendamentos()->create([
            'titulo' => 'Aula pública', 'inicio_em' => '2026-11-07 09:00', 'fim_em' => '2026-11-07 10:00',
            'capacidade' => 2, 'publicar_vagas' => true,
        ]);
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno interno', 'status' => 'ativo']);
        $event->alunos()->attach($student->id);
        $payload = [
            'eventId' => $event->id, 'date' => '2026-11-07', 'start' => '09:00', 'end' => '10:00',
            'name' => 'Visitante de teste', 'phone' => '(71) 99999-1234', 'requestId' => (string) Str::uuid(),
        ];
        $path = '/api/public/schedule/turmas/reservations';
        $this->postJson('/api/public/schedule/'.$other->slug.'/reservations', $payload)->assertConflict();
        $this->postJson($path, [...$payload, 'start' => '09:15'])->assertConflict();
        $this->postJson($path, [...$payload, 'phone' => '123'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $reference = $this->postJson($path, $payload)->assertCreated()->assertJsonMissingPath('reservation.phone')->json('reservation.reference');
        $this->postJson($path, [...$payload, 'phone' => '+55 71 99999-1234'])->assertOk()->assertJsonPath('reservation.reference', $reference);
        $this->postJson($path, [...$payload, 'requestId' => (string) Str::uuid()])->assertConflict();
        $this->postJson($path, [...$payload, 'phone' => '(71) 99999-5678', 'requestId' => (string) Str::uuid()])->assertConflict();
        $this->getJson('/api/public/schedule/turmas/availability?date=2026-11-07')->assertOk()->assertJsonCount(0, 'events');
        $this->getJson('/api/public/schedule/turmas')->assertOk()->assertJsonPath('availableDates', []);
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('public_appointment_reservations', 1);
        $this->assertDatabaseCount('staff_notifications', 1);
        $this->assertDatabaseCount('alunos', 1);

        $this->actingAs($owner)->getJson('/api/schedule?start=2026-11-07&end=2026-11-07')
            ->assertJsonPath('events.0.participantCount', 2)
            ->assertJsonPath('events.0.publicReservations.0.name', 'Visitante de teste')
            ->assertJsonPath('summary.availableSlots', 0);
        $this->getJson('/api/dashboard')->assertJsonPath('stats.3.value', '2');
        $this->putJson('/api/schedule/'.$event->id, [
            ...$this->payload(null, [$student->id]),
            'starts_at' => '2026-11-07T09:00', 'ends_at' => '2026-11-07T10:00', 'capacity' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('capacity');
    }

    public function test_new_appointments_need_same_day_and_published_appointments_need_capacity(): void
    {
        [$owner] = $this->organizationWithUser('proprietario', 'validar-vagas');
        $payload = $this->payload($owner->id);
        $this->actingAs($owner)->postJson('/api/schedule', [...$payload, 'ends_at' => '2026-08-04T10:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->postJson('/api/schedule', [...$payload, 'publish_spots' => true, 'capacity' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');
        $this->postJson('/api/schedule', [...$payload, 'publish_spots' => true])
            ->assertCreated()->assertJsonPath('event.publishSpots', true);
    }

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
