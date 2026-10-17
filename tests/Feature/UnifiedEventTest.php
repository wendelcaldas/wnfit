<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Organizacao;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedEventTest extends TestCase
{
    use RefreshDatabase;

    protected function context(string $role = 'proprietario'): array
    {
        $user = User::factory()->create();
        $org = Organizacao::create(['nome_fantasia' => 'Studio', 'slug' => Str::uuid(), 'tipo' => 'studio', 'ativa' => true]);
        $org->usuarios()->attach($user, ['papel' => $role, 'status' => 'ativo']);
        $event = Event::create(['organizacao_id' => $org->id, 'name' => 'Evento', 'organizer' => 'Studio', 'slug' => Str::uuid(), 'status' => 'published', 'audience' => 'open', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'checkin_opens_at' => now()->subHour()]);

        return [$user, $org, $event];
    }

    protected function student(Organizacao $org, string $kind = 'aluno'): StudentAccount
    {
        $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Pessoa Teste', 'perfil_tipo' => $kind, 'status' => $kind === 'aluno' ? 'ativo' : 'participante']);

        return StudentAccount::create(['aluno_id' => $student->id, 'username' => Str::lower(Str::random(12)), 'password' => 'SenhaTeste123', 'must_change_password' => false]);
    }

    protected function loginStudent(StudentAccount $account): void
    {
        $account->refresh();
        $this->actingAs($account, 'student')->withSession(['student_credential_version' => $account->credential_version]);
    }

    public function test_public_registration_creates_only_participant_and_does_not_disclose_existing_profile(): void
    {
        [$owner,$org,$event] = $this->context();
        $url = '/api/public/events/'.$event->slug;
        $this->postJson($url.'/register', ['name' => 'Visitante Novo', 'phone' => '71999991234', 'privacy' => true])->assertCreated();
        $this->assertDatabaseHas('alunos', ['perfil_tipo' => 'participante', 'status' => 'participante']);
        $this->assertDatabaseCount('student_accounts', 0);
        $this->assertDatabaseCount('assinaturas', 0);
        $this->assertDatabaseCount('cobrancas', 0);
        $this->postJson($url.'/register', ['name' => 'Outra Pessoa', 'phone' => '71999991234', 'privacy' => true])->assertUnprocessable()->assertJsonMissingPath('student');
        $this->actingAs($owner)->getJson('/api/students')->assertOk()->assertJsonCount(0, 'students');
        $this->getJson('/api/students?tipo=participante')->assertOk()->assertJsonCount(1, 'students');
    }

    public function test_gifts_preserve_snapshots_stock_and_delivery(): void
    {
        [$owner,$org,$event] = $this->context();
        $event->update(['modules' => ['gift' => ['enabled' => true, 'name' => 'Nécessaire', 'quantity' => 1, 'message' => 'Cuide-se', 'instructions' => 'Equipe']]]);
        $first = $this->student($org);
        $second = $this->student($org);
        foreach ([$first, $second] as $account) {
            $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['student_id' => $account->aluno_id])->assertOk();
        }
        $this->loginStudent($first);
        $this->getJson("/api/student/events/{$event->id}/gift")->assertOk()->assertJsonPath('gift', null);
        $this->postJson("/api/student/events/{$event->id}/gift")->assertOk()->assertJsonPath('gift.name', 'Nécessaire');
        $event->update(['modules' => ['gift' => ['enabled' => true, 'name' => 'Alterado', 'quantity' => 1]]]);
        $this->postJson("/api/student/events/{$event->id}/gift")->assertOk()->assertJsonPath('gift.name', 'Nécessaire');
        $this->loginStudent($second);
        $this->postJson("/api/student/events/{$event->id}/gift")->assertUnprocessable();
        $registration = $event->registrations()->where('aluno_id', $first->aluno_id)->first();
        $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations/{$registration->id}/gift-delivery")->assertOk();
        $time = $registration->fresh()->gift_delivered_at;
        $this->postJson("/api/events/{$event->id}/registrations/{$registration->id}/gift-delivery")->assertOk();
        $this->assertTrue($time->equalTo($registration->fresh()->gift_delivered_at));
        $this->assertEquals($owner->id, $registration->fresh()->gift_delivered_by);
        $event->update(['modules' => ['gift' => ['enabled' => false]]]);
        $this->loginStudent($first);
        $this->getJson("/api/student/events/{$event->id}/gift")->assertNotFound();
        $this->assertNotNull($registration->fresh()->gift_snapshot);
    }

    public function test_album_requires_consent_and_moderation_and_protects_files(): void
    {
        Storage::fake('local');
        [$owner,$org,$event] = $this->context();
        $event->update(['modules' => ['album' => ['enabled' => true]]]);
        $first = $this->student($org);
        $second = $this->student($org);
        foreach ([$first, $second] as $account) {
            $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['student_id' => $account->aluno_id])->assertOk();
        }
        $base = "/api/student/events/{$event->id}/photos";
        $this->loginStudent($first);
        $this->postJson($base, ['photo' => UploadedFile::fake()->image('foto.jpg', 100, 80)])->assertUnprocessable();
        $response = $this->postJson($base, ['photo' => UploadedFile::fake()->image('foto.jpg', 100, 80), 'consent' => true])->assertCreated()->assertJsonPath('photo.status', 'pending');
        $id = $response->json('photo.id');
        $photo = EventPhoto::findOrFail($id);
        Storage::disk('local')->assertExists([$photo->path, $photo->thumbnail_path]);
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data');
        $this->get($base."/{$id}/file")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->loginStudent($second);
        $this->getJson($base)->assertJsonCount(0, 'data');
        $this->get($base."/{$id}/file")->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/events/{$event->id}/photos/{$id}", ['status' => 'approved'])->assertOk();
        $this->loginStudent($second);
        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data');
        $this->get($base."/{$id}/file?thumbnail=1")->assertOk();
        $this->actingAs($owner)->patchJson("/api/events/{$event->id}/photos/{$id}", ['status' => 'removed'])->assertOk();
        $this->loginStudent($second);
        $this->get($base."/{$id}/file")->assertNotFound();
        $event->update(['modules' => ['album' => ['enabled' => false]]]);
        $this->getJson($base)->assertNotFound();
        $this->assertDatabaseCount('event_photos', 1);
    }

    public function test_teacher_can_add_existing_students_with_shared_or_absent_phones_and_not_change_event(): void
    {
        [$teacher,$org,$event] = $this->context('professor');
        $this->actingAs($teacher);
        foreach (['71999991234', '71999991234', null] as $phone) {
            $student = Aluno::create(['organizacao_id' => $org->id, 'nome' => 'Aluno', 'telefone' => $phone]);
            $this->postJson("/api/events/{$event->id}/registrations", ['student_id' => $student->id])->assertOk();
            $this->postJson("/api/events/{$event->id}/registrations", ['student_id' => $student->id])->assertOk();
        }
        $this->assertDatabaseCount('event_registrations', 3);
        $registration = $event->registrations()->first();
        $this->assertNull($registration->privacy_accepted_at);
        $this->assertFalse($registration->marketing_opt_in);
        $this->assertEquals($teacher->id, $registration->created_by);
        $this->patchJson("/api/events/{$event->id}/registrations/{$registration->id}", ['present' => true])->assertOk();
        $this->patchJson("/api/events/{$event->id}", [])->assertForbidden();
    }

    public function test_student_self_registration_reuses_profile_and_enforces_audience_and_tenant(): void
    {
        [,$org,$event] = $this->context();
        $event->update(['audience' => 'students']);
        $this->postJson('/api/public/events/'.$event->slug.'/register', ['name' => 'Visitante', 'phone' => '71999991234', 'privacy' => true])->assertUnprocessable();
        $guest = $this->student($org, 'participante');
        $this->loginStudent($guest);
        $this->postJson("/api/student/events/{$event->id}/register", ['privacy' => true])->assertUnprocessable();
        $account = $this->student($org);
        $this->loginStudent($account);
        $this->postJson("/api/student/events/{$event->id}/register", ['privacy' => true])->assertOk();
        $this->postJson("/api/student/events/{$event->id}/register", ['privacy' => true])->assertOk();
        $this->getJson('/api/public/events/'.$event->slug)->assertOk()->assertJsonPath('student.name', 'Pessoa Teste')->assertJsonPath('participant.name', 'Pessoa Teste');
        $this->assertDatabaseCount('event_registrations', 1);
        [,$otherOrg] = $this->context();
        $this->loginStudent($this->student($otherOrg));
        $this->postJson("/api/student/events/{$event->id}/register", ['privacy' => true])->assertNotFound();
    }

    public function test_capacity_is_shared_between_public_and_staff_registration(): void
    {
        [$owner,$org,$event] = $this->context();
        $event->update(['capacity' => 1]);
        $this->postJson('/api/public/events/'.$event->slug.'/register', ['name' => 'Visitante', 'phone' => '71999991234', 'privacy' => true])->assertCreated();
        $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['name' => 'Outro Visitante'])->assertUnprocessable();
        $this->assertDatabaseCount('alunos', 1);
    }

    public function test_event_page_is_scoped_and_welcome_is_persisted(): void
    {
        [$owner,$org,$event] = $this->context();
        $account = $this->student($org);
        $this->actingAs($owner)->postJson("/api/events/{$event->id}/registrations", ['student_id' => $account->aluno_id])->assertOk();
        $this->loginStudent($account);
        $this->getJson('/api/student/me')->assertJsonPath('student.canAccessEvents', true);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(1);
        $this->getJson("/api/student/events/{$event->id}")->assertOk()->assertJsonPath('welcomed', false);
        $this->postJson("/api/student/events/{$event->id}/welcome")->assertOk();
        $time = $event->registrations()->first()->welcomed_at;
        $this->postJson("/api/student/events/{$event->id}/welcome")->assertOk();
        $this->assertEquals($time, $event->registrations()->first()->welcomed_at);
        $this->getJson("/api/student/events/{$event->id}")->assertJsonPath('welcomed', true);
        $this->loginStudent($this->student($org));
        $this->getJson("/api/student/events/{$event->id}")->assertNotFound();
        $event->update(['status' => 'draft']);
        $this->loginStudent($account);
        $this->getJson("/api/student/events/{$event->id}")->assertNotFound();
    }

    public function test_modules_are_per_event_and_configuration_is_manager_only(): void
    {
        [$owner,$org,$event] = $this->context();
        [,$otherOrg,$otherEvent] = $this->context();
        $modules = ['gift' => ['enabled' => true, 'name' => 'Nécessaire', 'quantity' => 20], 'album' => ['enabled' => false], 'feedback' => ['enabled' => false]];
        $this->actingAs($owner)->patchJson("/api/events/{$event->id}/settings", ['modules' => $modules])->assertOk();
        $this->assertTrue($event->fresh()->moduleSettings()['gift']['enabled']);
        $this->assertFalse($otherEvent->fresh()->moduleSettings()['gift']['enabled']);
        $this->patchJson("/api/events/{$otherEvent->id}/settings", ['modules' => $modules])->assertNotFound();
        $owner->organizacoes()->updateExistingPivot($org->id, ['papel' => 'professor']);
        $this->patchJson("/api/events/{$event->id}/settings", ['modules' => $modules])->assertForbidden();
    }
}
