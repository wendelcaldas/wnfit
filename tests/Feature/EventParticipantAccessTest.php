<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Event;
use App\Models\Organizacao;
use App\Models\StudentAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventParticipantAccessTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $role = 'proprietario', string $membership = 'ativo'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::create(['nome_fantasia' => 'Team Peu', 'slug' => Str::uuid(), 'tipo' => 'studio', 'ativa' => true]);
        $organization->usuarios()->attach($user, ['papel' => $role, 'status' => $membership]);
        $event = Event::create(['organizacao_id' => $organization->id, 'slug' => Str::uuid(), 'name' => 'Encontro', 'organizer' => 'Team Peu', 'status' => 'published']);
        $registration = $event->registrations()->create(['name' => 'Pessoa Convidada', 'phone' => '+5571999991234', 'access_hash' => hash('sha256', Str::uuid()), 'recovery_hash' => hash('sha256', Str::uuid()), 'privacy_accepted_at' => now()]);

        return [$user, $event, $registration, "/api/events/{$event->id}/registrations/{$registration->id}"];
    }

    public function test_new_participant_has_access_without_subscription_charges_or_messages_and_retries_preserve_password(): void
    {
        [$user, , $registration, $url] = $this->fixture();
        $first = $this->actingAs($user)->postJson($url.'/access', ['mode' => 'create'])->assertOk();
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $credentials = $first->json('credentials');
        $studentId = $first->json('student.id');
        $this->assertSame($studentId, $registration->fresh()->aluno_id);
        foreach (['assinaturas', 'cobrancas', 'pagamentos', 'mensagens', 'planos'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('alunos', 1);
        $this->postJson($url.'/access', ['mode' => 'create'])->assertOk()->assertJsonPath('credentials', null)->assertJsonPath('student.id', $studentId);
        $this->assertDatabaseCount('student_accounts', 1);
        $account = StudentAccount::first();
        $this->assertTrue(Hash::check($credentials['temporaryPassword'], $account->password));
        $this->assertSame(1, $account->credential_version);
        $this->postJson('/api/student/login', ['username' => $credentials['username'], 'password' => $credentials['temporaryPassword']])->assertOk()->assertJsonPath('student.mustChangePassword', true);
        $this->getJson('/api/student/home')->assertForbidden();
        $this->putJson('/api/student/password', ['current_password' => $credentials['temporaryPassword'], 'password' => 'NovaSenha12345', 'password_confirmation' => 'NovaSenha12345'])->assertOk()->assertJsonPath('student.mustChangePassword', false);
        $this->getJson('/api/student/home')->assertOk();
    }

    public function test_linking_an_existing_account_preserves_credentials_and_student_fields(): void
    {
        [$user, $event, $registration, $url] = $this->fixture();
        $student = Aluno::create(['organizacao_id' => $event->organizacao_id, 'nome' => 'Aluna Existente', 'telefone' => '(71) 99999-1234', 'plano' => 'Semestral', 'vencimento' => '2027-01-01']);
        $account = StudentAccount::create(['aluno_id' => $student->id, 'username' => 'aluna', 'password' => 'SenhaAtual123', 'must_change_password' => false, 'credential_version' => 5]);
        $hash = $account->password;
        $this->actingAs($user)->postJson($url.'/access', ['mode' => 'link', 'student_id' => $student->id])->assertOk()->assertJsonPath('credentials', null)->assertJsonPath('student.username', 'aluna');
        $this->assertSame($hash, $account->fresh()->password);
        $this->assertFalse($account->fresh()->must_change_password);
        $this->assertSame(5, $account->fresh()->credential_version);
        $this->assertSame('Semestral', $student->fresh()->plano);
        $this->assertSame('2027-01-01', $student->fresh()->vencimento->toDateString());
        $this->getJson('/api/events/'.$event->id.'/registrations')->assertOk()->assertJsonPath('data.0.aluno.id', $student->id);
    }

    public function test_existing_student_without_account_receives_one_and_can_participate_in_multiple_events(): void
    {
        [$user, $event, , $url] = $this->fixture();
        $student = Aluno::create(['organizacao_id' => $event->organizacao_id, 'nome' => 'Pessoa Existente']);
        $this->actingAs($user)->postJson($url.'/access', ['mode' => 'link', 'student_id' => $student->id])->assertOk()->assertJsonPath('student.id', $student->id)->assertJsonStructure(['credentials' => ['username', 'temporaryPassword']]);
        $anotherEvent = $event->replicate();
        $anotherEvent->slug = Str::uuid();
        $anotherEvent->save();
        $otherRegistration = $event->registrations()->first()->replicate();
        $otherRegistration->event_id = $anotherEvent->id;
        $otherRegistration->aluno_id = null;
        $otherRegistration->access_hash = hash('sha256', Str::uuid());
        $otherRegistration->recovery_hash = hash('sha256', Str::uuid());
        $otherRegistration->save();
        $this->postJson("/api/events/{$anotherEvent->id}/registrations/{$otherRegistration->id}/access", ['mode' => 'link', 'student_id' => $student->id])->assertOk()->assertJsonPath('credentials', null);
        $this->assertDatabaseCount('alunos', 1);
        $this->assertDatabaseCount('student_accounts', 1);
    }

    public function test_formatted_phone_matches_are_suggested_and_block_accidental_duplicate_creation(): void
    {
        [$user, $event, , $url] = $this->fixture();
        $student = Aluno::create(['organizacao_id' => $event->organizacao_id, 'nome' => 'Nome Diferente', 'telefone' => '(71) 99999-1234']);
        $this->actingAs($user)->getJson($url.'/students')->assertOk()->assertJsonPath('data.0.id', $student->id)->assertJsonPath('data.0.phoneMatch', true)->assertJsonMissingPath('data.0.password');
        $this->getJson($url.'/students?q=%2B5571999991234')->assertOk()->assertJsonPath('data.0.id', $student->id);
        $this->postJson($url.'/access', ['mode' => 'create'])->assertUnprocessable();
        $this->assertDatabaseCount('alunos', 1);
        $this->assertDatabaseCount('student_accounts', 0);
    }

    public function test_tenant_roles_and_registration_scopes_are_enforced(): void
    {
        [$owner, $event, , $url] = $this->fixture();
        [$outsider, $otherEvent, $otherRegistration] = $this->fixture();
        $foreignStudent = Aluno::create(['organizacao_id' => $otherEvent->organizacao_id, 'nome' => 'Aluno Externo', 'telefone' => '+5571999991234']);
        $this->postJson($url.'/access', ['mode' => 'create'])->assertUnauthorized();
        $this->actingAs($outsider)->getJson($url.'/students')->assertNotFound();
        $this->postJson($url.'/access', ['mode' => 'create'])->assertNotFound();
        $this->actingAs($owner)->getJson($url.'/students')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson($url.'/access', ['mode' => 'link', 'student_id' => $foreignStudent->id])->assertNotFound();
        $this->postJson("/api/events/{$event->id}/registrations/{$otherRegistration->id}/access", ['mode' => 'create'])->assertNotFound();
        $owner->organizacoes()->updateExistingPivot($event->organizacao_id, ['papel' => 'professor']);
        $this->getJson($url.'/students')->assertForbidden();
        $this->postJson($url.'/access', ['mode' => 'create'])->assertForbidden();
        $owner->organizacoes()->updateExistingPivot($event->organizacao_id, ['papel' => 'administrador', 'status' => 'inativo']);
        $this->postJson($url.'/access', ['mode' => 'create'])->assertForbidden();
    }

    public function test_same_student_cannot_be_linked_twice_or_silently_replaced(): void
    {
        [$owner, $event, $registration, $url] = $this->fixture();
        $this->actingAs($owner)->postJson($url.'/access', ['mode' => 'create'])->assertOk();
        $student = Aluno::create(['organizacao_id' => $event->organizacao_id, 'nome' => 'Outro Aluno']);
        $this->postJson($url.'/access', ['mode' => 'link', 'student_id' => $student->id])->assertUnprocessable();
        $another = $registration->replicate();
        $another->phone = '+5571999991235';
        $another->aluno_id = null;
        $another->access_hash = hash('sha256', Str::uuid());
        $another->recovery_hash = hash('sha256', Str::uuid());
        $another->save();
        $target = "/api/events/{$event->id}/registrations/{$another->id}";
        $this->postJson($target.'/access', ['mode' => 'link', 'student_id' => $registration->fresh()->aluno_id])->assertUnprocessable();
        $this->getJson($target.'/students')->assertOk()->assertJsonPath('data.1.linkedToAnotherParticipant', true);
        $this->assertNull($another->fresh()->aluno_id);
    }

    public function test_invalid_requests_and_cancelled_events_do_not_create_students(): void
    {
        [$owner, $event, , $url] = $this->fixture();
        $this->actingAs($owner)->postJson($url.'/access', ['mode' => 'link'])->assertUnprocessable();
        $this->postJson($url.'/access', ['mode' => 'wrong'])->assertUnprocessable();
        $this->postJson($url.'/access', ['mode' => 'create', 'student_id' => 1])->assertUnprocessable();
        $event->update(['status' => 'cancelled']);
        $this->postJson($url.'/access', ['mode' => 'create'])->assertUnprocessable();
        $this->assertDatabaseCount('alunos', 0);
    }
}
