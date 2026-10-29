<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Models\OrganizationBookingLink;
use App\Models\User;
use App\Services\PublicBookingNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusiness(string $slug = 'hours'): array
    {
        $this->travelTo(Carbon::parse('2026-11-02 09:00:00'));
        $u = User::factory()->create();
        $o = Organizacao::create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true]);
        $o->usuarios()->attach($u->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$u, $o];
    }

    private function config(int $id): array
    {
        return ['enforced' => true, 'weeklyHours' => [[], [['start' => '09:00', 'end' => '12:00']], [], [], [], [], []], 'publicEnabled' => true, 'durationMinutes' => 60, 'instructorId' => $id, 'title' => 'Avaliação', 'location' => 'Estúdio'];
    }

    private function event(int $id, string $time = '10:00'): array
    {
        return ['title' => 'Aula', 'type' => 'individual', 'status' => 'agendado', 'starts_at' => '2026-11-02T'.$time, 'ends_at' => '2026-11-02T'.((int) substr($time, 0, 2) + 1).':00', 'instructor_id' => $id, 'modality' => 'presencial', 'participant_ids' => []];
    }

    private function enable(User $u): string
    {
        return $this->actingAs($u)->putJson('/api/organization/schedule-settings', $this->config($u->id))->assertOk()->json('settings.publicToken');
    }

    public function test_settings_are_tenant_scoped_and_validate_permissions_and_intervals(): void
    {
        [$u,$o] = $this->setupBusiness();
        [$other] = $this->setupBusiness('other');
        $this->actingAs($u)->getJson('/api/organization/schedule-settings')->assertJsonPath('settings.enforced', false);
        $c = $this->config($other->id);
        $this->putJson('/api/organization/schedule-settings', $c)->assertUnprocessable();
        $c = $this->config($u->id);
        $c['weeklyHours'][1][] = ['start' => '11:00', 'end' => '13:00'];
        $this->putJson('/api/organization/schedule-settings', $c)->assertUnprocessable();
        $this->enable($u);
        $this->assertDatabaseCount('organization_schedule_settings', 1);
        $this->actingAs($other)->getJson('/api/organization/schedule-settings')->assertJsonPath('settings.enforced', false);
        $o->usuarios()->updateExistingPivot($u->id, ['papel' => 'professor']);
        $this->actingAs($u)->putJson('/api/organization/schedule-settings', $this->config($u->id))->assertForbidden();
    }

    public function test_manual_schedule_enforces_hours_but_preserves_legacy_edits_and_cancellation(): void
    {
        [$u,$o] = $this->setupBusiness();
        $e = $this->event($u->id, '07:00');
        $id = $this->actingAs($u)->postJson('/api/schedule', $e)->assertCreated()->json('event.id');
        $this->enable($u);
        $this->postJson('/api/schedule', $e)->assertUnprocessable();
        $e['title'] = 'Editado';
        $this->putJson('/api/schedule/'.$id, $e)->assertOk();
        $this->getJson('/api/schedule?start=2026-11-02&end=2026-11-02')->assertJsonCount(1, 'events')->assertJsonPath('operatingHours.enforced', true);
        $e['status'] = 'cancelado';
        $this->putJson('/api/schedule/'.$id, $e)->assertOk();
        $this->postJson('/api/schedule', $this->event($u->id))->assertCreated();
        $e = $this->event($u->id, '11:00');
        $e['repeat_frequency'] = 'monthly';
        $e['repeat_until'] = '2026-12-02';
        $this->postJson('/api/schedule', $e)->assertUnprocessable();
        $this->assertDatabaseCount('agendamentos', 2);
    }

    public function test_public_slots_hide_private_data_and_respect_conflicts_scope_and_lead_time(): void
    {
        [$u,$o] = $this->setupBusiness();
        $token = $this->enable($u);
        [, $other] = $this->setupBusiness('other');
        foreach ([[$o, null, '10:00', 'agendado'], [$o, $u->id, '11:00', 'cancelado'], [$other, null, '11:00', 'agendado']] as [$org,$teacher,$time,$status]) {
            Agendamento::create(['organizacao_id' => $org->id, 'titulo' => 'Segredo', 'inicio_em' => '2026-11-02 '.$time, 'fim_em' => '2026-11-02 '.((int) substr($time, 0, 2) + 1).':00', 'instrutor_id' => $teacher, 'status' => $status]);
        }
        $this->getJson('/api/public/booking/'.$token)->assertOk()->assertJsonMissingPath('students')->assertJsonMissingPath('events');
        $this->getJson('/api/public/booking/'.$token.'/availability?date=2026-11-02')->assertOk()->assertJsonCount(1, 'slots')->assertJsonPath('slots.0.label', '11:00');
        $this->getJson('/api/public/booking/'.$token.'/availability?date=2027-04-01')->assertUnprocessable();
        $o->usuarios()->updateExistingPivot($u->id, ['status' => 'inativo']);
        $this->getJson('/api/public/booking/'.$token)->assertNotFound();
    }

    public function test_public_booking_is_idempotent_and_rechecks_occupancy_without_enrollment(): void
    {
        [$u,$o] = $this->setupBusiness();
        $token = $this->enable($u);
        $url = '/api/public/booking/'.$token;
        $p = ['startsAt' => '2026-11-02T10:00:00'.now()->format('P'), 'name' => 'Visitante', 'phone' => '11999999999', 'requestId' => (string) Str::uuid()];
        $this->postJson($url, $p)->assertCreated()->assertJsonPath('booking.status', 'agendado');
        $this->postJson($url, $p)->assertOk();
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('public_schedule_bookings', 1);
        $this->assertDatabaseCount('alunos', 0);
        $p['name'] = 'Outro';
        $this->postJson($url, $p)->assertConflict();
        $p['requestId'] = (string) Str::uuid();
        $this->postJson($url, $p)->assertConflict();
        $p['startsAt'] = '2026-11-02T11:00:01'.now()->format('P');
        $this->postJson($url, $p)->assertConflict();
        $this->actingAs($u)->postJson('/api/schedule', $this->event($u->id))->assertUnprocessable();
        $this->getJson('/api/schedule?start=2026-11-02&end=2026-11-02')->assertJsonPath('events.0.publicBooking.name', 'Visitante');
        OrganizationBookingLink::where('organizacao_id', $o->id)->update(['active' => false]);
        $this->postJson($url, $p)->assertNotFound();
    }

    public function test_multiple_links_have_stable_tokens_limit_and_shared_conflicts(): void
    {
        [$u,$o] = $this->setupBusiness();
        $legacy = $this->enable($u);
        $body = ['title' => 'Consulta', 'durationMinutes' => 30, 'instructorId' => $u->id, 'location' => 'Sala 2', 'active' => true];
        $url = '/api/organization/booking-links';
        $r = $this->postJson($url, $body)->assertCreated();
        $link = $r->json('settings.bookingLinks.1');
        $this->postJson($url, [...$body, 'title' => 'Terceiro'])->assertCreated()->assertJsonCount(3, 'settings.bookingLinks');
        $this->postJson($url, $body)->assertUnprocessable();
        $this->putJson($url.'/'.$link['id'], [...$body, 'title' => 'Nova consulta', 'active' => false])->assertOk()->assertJsonPath('settings.bookingLinks.1.publicToken', $link['publicToken']);
        $this->getJson('/api/public/booking/'.$link['publicToken'])->assertNotFound();
        $this->putJson($url.'/'.$link['id'], $body)->assertOk();
        $p = ['startsAt' => '2026-11-02T10:00:00'.now()->format('P'), 'name' => 'Pessoa', 'phone' => '11999999999', 'requestId' => (string) Str::uuid()];
        $this->postJson('/api/public/booking/'.$legacy, $p)->assertCreated();
        $this->postJson('/api/public/booking/'.$link['publicToken'], $p)->assertConflict();
        $p['requestId'] = (string) Str::uuid();
        $this->postJson('/api/public/booking/'.$link['publicToken'], $p)->assertConflict();
        $this->deleteJson($url.'/'.$r->json('settings.bookingLinks.0.id'))->assertOk();
        $this->assertDatabaseHas('public_schedule_bookings', ['booking_link_id' => null]);
        $this->getJson('/api/public/booking/'.$legacy)->assertNotFound();
        $this->deleteJson($url.'/'.$link['id'])->assertOk();
        $this->assertDatabaseCount('agendamentos', 1);
        $this->postJson($url, $body)->assertCreated();
        [$other] = $this->setupBusiness('foreign');
        $this->actingAs($other)->deleteJson($url.'/'.$r->json('settings.bookingLinks.0.id'))->assertNotFound();
        $this->assertDatabaseCount('agendamentos', 1);
    }

    public function test_existing_single_link_and_bookings_are_preserved_by_migration(): void
    {
        [$u,$o] = $this->setupBusiness();
        $token = $this->enable($u);
        $this->postJson('/api/public/booking/'.$token, ['startsAt' => '2026-11-02T10:00:00'.now()->format('P'), 'name' => 'Legado', 'phone' => '11999999999', 'requestId' => (string) Str::uuid()])->assertCreated();
        $migration = require database_path('migrations/2026_10_29_000004_create_organization_booking_links.php');
        $migration->down();
        $migration->up();
        $this->getJson('/api/public/booking/'.$token)->assertOk();
        $this->assertDatabaseCount('agendamentos', 1);
        $this->assertDatabaseCount('public_schedule_bookings', 1);
        $id = OrganizationBookingLink::where('public_token', $token)->value('id');
        $this->assertDatabaseHas('public_schedule_bookings', ['booking_link_id' => $id, 'booking_token' => $token]);
    }

    public function test_public_booking_notifications_have_private_recipients_and_idempotent_read_state(): void
    {
        [$owner,$org] = $this->setupBusiness();
        $teacher = User::factory()->create(['name' => 'Professor responsável']);
        $admin = User::factory()->create();
        $unrelated = User::factory()->create();
        $inactive = User::factory()->create();
        foreach ([[$teacher, 'professor', 'ativo'], [$admin, 'administrador', 'ativo'], [$unrelated, 'professor', 'ativo'], [$inactive, 'administrador', 'inativo']] as [$user,$role,$status]) {
            $org->usuarios()->attach($user->id, ['papel' => $role, 'status' => $status]);
        }
        [$foreign] = $this->setupBusiness('notification-foreign');
        $settings = $this->config($teacher->id);
        $token = $this->actingAs($owner)->putJson('/api/organization/schedule-settings', $settings)->assertOk()->json('settings.publicToken');
        $p = ['startsAt' => '2026-11-02T10:00:00'.now()->format('P'), 'name' => 'Nova pessoa', 'phone' => '11999999999', 'requestId' => (string) Str::uuid()];
        $url = '/api/public/booking/'.$token;
        $this->postJson($url, $p)->assertCreated();
        $this->postJson($url, $p)->assertOk();
        $this->assertDatabaseCount('staff_notifications', 3);
        foreach ([$owner, $admin, $teacher] as $user) {
            $this->assertDatabaseHas('staff_notifications', ['user_id' => $user->id, 'type' => 'public_booking_created']);
        }
        foreach ([$unrelated, $inactive, $foreign] as $user) {
            $this->assertDatabaseMissing('staff_notifications', ['user_id' => $user->id]);
        }
        $n = $this->actingAs($owner)->getJson('/api/notifications')->assertOk()->assertJsonPath('unreadCount', 1)->assertJsonPath('notifications.0.data.guestName', 'Nova pessoa')->assertJsonPath('notifications.0.data.instructorName', 'Professor responsável')->json('notifications.0.id');
        $this->patchJson('/api/notifications/'.$n.'/read')->assertOk()->assertJsonPath('unreadCount', 0);
        $this->actingAs($teacher)->getJson('/api/notifications')->assertJsonPath('unreadCount', 1);
        $this->actingAs($foreign)->patchJson('/api/notifications/'.$n.'/read')->assertNotFound();
        $p['requestId'] = (string) Str::uuid();
        $this->postJson($url, $p)->assertConflict();
        $this->assertDatabaseCount('staff_notifications', 3);
    }

    public function test_notification_failure_rolls_back_the_public_booking(): void
    {
        [$u] = $this->setupBusiness();
        $token = $this->enable($u);
        $this->mock(PublicBookingNotificationService::class, function ($mock) {
            $mock->shouldReceive('created')->once()->andThrow(new \RuntimeException('notification unavailable'));
        });
        $this->postJson('/api/public/booking/'.$token, ['startsAt' => '2026-11-02T10:00:00'.now()->format('P'), 'name' => 'Pessoa', 'phone' => '11999999999', 'requestId' => (string) Str::uuid()])->assertStatus(500);
        $this->assertDatabaseCount('agendamentos', 0);
        $this->assertDatabaseCount('public_schedule_bookings', 0);
        $this->assertDatabaseCount('staff_notifications',0);
    }
}
