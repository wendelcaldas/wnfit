<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => 'Team Peu', 'slug' => uniqid('peu'), 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => 'proprietario', 'status' => 'ativo']);
        $event = Event::create(['organizacao_id' => $org->id, 'slug' => uniqid('praia'), 'name' => 'Aulão', 'organizer' => 'Team Peu', 'status' => 'published', 'starts_at' => now()->subHours(4), 'ends_at' => now()->subHour(), 'checkin_opens_at' => now()->subHours(4)]);

        return [$user, $event, '/api/public/events/'.$event->slug];
    }

    private function signup(string $url)
    {
        return $this->postJson($url.'/register', ['name' => 'Pessoa Teste', 'phone' => '(71) 99999-1234', 'privacy' => true]);
    }

    public function test_registration_is_separate_and_recovery_authenticates_without_phone_lookup(): void
    {
        [, $event, $url] = $this->fixture();
        $response = $this->signup($url)->assertCreated()->assertJsonMissingPath('participant.phone')->assertJsonMissingPath('participant.access_hash');
        $this->assertDatabaseCount('alunos', 0);
        $this->assertDatabaseCount('cobrancas', 0);
        $this->assertDatabaseHas('event_registrations', ['phone' => '+5571999991234', 'marketing_opt_in' => false]);
        $this->getJson($url)->assertJsonPath('participant', null);
        $this->signup($url)->assertConflict();
        $this->postJson($url.'/recover', ['code' => '5571999991234'])->assertUnprocessable();
        $recovered = $this->postJson($url.'/recover', ['code' => $response->json('recoveryCode')])->assertOk();
        $cookie = $recovered->getCookie('wnfit_event_'.$event->id, false);
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->getJson($url)->assertJsonPath('participant.name', 'Pessoa Teste');
    }

    public function test_attendance_remains_open_after_expected_end_until_manual_close(): void
    {
        [$user, $event, $url] = $this->fixture();
        $response = $this->signup($url)->assertCreated();
        $cookie = $response->getCookie('wnfit_event_'.$event->id, false);
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        $this->postJson($url.'/feedback', ['rating' => 5])->assertUnprocessable();
        $this->postJson($url.'/checkin')->assertOk()->assertJsonPath('participant.attendanceSource', 'self');
        $time = $event->registrations()->first()->checked_in_at;
        $this->postJson($url.'/checkin')->assertOk();
        $this->assertTrue($time->equalTo($event->registrations()->first()->checked_in_at));
        $this->actingAs($user)->postJson('/api/events/'.$event->id.'/close')->assertOk();
        $this->getJson($url)->assertJsonPath('event.finished', true)->assertJsonPath('event.checkinOpen', false);
        $this->postJson($url.'/checkin')->assertUnprocessable();
        $this->postJson($url.'/feedback', ['rating' => 5, 'feedback' => 'Muito bom'])->assertOk();
        $this->postJson($url.'/feedback', ['rating' => 6])->assertUnprocessable();
        $this->signup($url)->assertUnprocessable();
    }

    public function test_drafts_capacity_timing_and_tenant_authorization(): void
    {
        [$user, $event, $url] = $this->fixture();
        [$other] = $this->fixture();
        $event->update(['status' => 'draft']);
        $this->getJson($url)->assertNotFound();
        $this->actingAs($other)->getJson('/api/events/'.$event->id.'/registrations')->assertNotFound();
        $this->actingAs($other)->postJson('/api/events/'.$event->id.'/close')->assertNotFound();
        $this->actingAs($user)->getJson($url)->assertOk();
        $event->update(['status' => 'published', 'capacity' => 1, 'checkin_opens_at' => now()->addDay()]);
        $response = $this->signup($url)->assertCreated();
        $this->postJson($url.'/register', ['name' => 'Outra Pessoa', 'phone' => '71999991235', 'privacy' => true])->assertUnprocessable();
        $cookie = $response->getCookie('wnfit_event_'.$event->id, false);
        $this->withCredentials()->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->postJson($url.'/checkin')->assertUnprocessable();
        $event->update(['checkin_opens_at' => now()->subHour()]);
        $participant = $event->registrations()->first();
        $this->patchJson('/api/events/'.$event->id.'/registrations/'.$participant->id, ['present' => false])->assertOk();
        $this->postJson($url.'/checkin')->assertUnprocessable();
    }

    public function test_manager_save_validates_dates_and_ignores_foreign_org_input(): void
    {
        [$user, $event] = $this->fixture();
        $data = ['name' => 'Evento', 'organizer' => 'Peu', 'location' => 'Ipitanga', 'starts_at' => '2026-10-18T06:00:00-03:00', 'ends_at' => '2026-10-18T09:00:00-03:00', 'checkin_opens_at' => '2026-10-18T06:00:00-03:00', 'status' => 'draft', 'capacity' => null, 'organizacao_id' => 999];
        $this->actingAs($user)->postJson('/api/events', $data)->assertOk()->assertJsonPath('organizacao_id', $event->organizacao_id)->assertJsonPath('starts_at', '2026-10-18T09:00:00.000000Z');
        $this->postJson('/api/events', [...$data, 'ends_at' => '2026-10-17'])->assertUnprocessable();
    }
}
