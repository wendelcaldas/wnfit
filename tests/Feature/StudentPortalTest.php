<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Exercicio;
use App\Models\Organizacao;
use App\Models\StudentAccount;
use App\Models\StudentWorkoutSession;
use App\Models\Treino;
use App\Models\User;
use App\Services\StudentAccessService;
use App\Services\WorkoutPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    private function setupStudent(string $slug = 'studio'): array
    {
        $owner = User::factory()->create();
        $organization = Organizacao::create(['nome_fantasia' => 'Studio de teste', 'slug' => $slug, 'tipo' => 'studio', 'ativa' => true]);
        $organization->usuarios()->attach($owner, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Wendel Souza Caldas', 'status' => 'ativo']);

        return [$owner, $organization, $student];
    }

    private function workout(User $owner, Organizacao $organization): Treino
    {
        $exercise = Exercicio::create(['nome' => 'Supino reto', 'grupo_muscular' => 'Peito', 'instrucoes' => 'Controle a descida.']);
        $workout = Treino::create(['organizacao_id' => $organization->id, 'criado_por' => $owner->id, 'nome' => 'Programa ABC', 'objetivo' => 'Hipertrofia', 'status' => 'ativo', 'duracao_semanas' => 8]);
        $day = $workout->dias()->create(['nome' => 'Treino A', 'foco' => 'Peito']);
        $day->exercicios()->create(['exercicio_id' => $exercise->id, 'nome' => 'Supino reto', 'series' => 3, 'repeticoes' => '8-12', 'carga' => '20 kg', 'descanso_segundos' => 60]);

        return $workout;
    }

    private function loginStudent(Aluno $student, bool $ready = true): array
    {
        $credentials = app(StudentAccessService::class)->issue($student);
        if ($ready) {
            StudentAccount::where('aluno_id', $student->id)->update(['must_change_password' => false]);
        }
        $this->postJson('/api/student/login', ['username' => $credentials['username'], 'password' => $credentials['temporaryPassword']])->assertOk();

        return $credentials;
    }

    public function test_registration_creates_unique_access_atomically_and_only_returns_temporary_password_once(): void
    {
        [$owner] = $this->setupStudent();
        $data = ['nome' => 'Wendel Souza Caldas', 'telefone' => '(71) 99999-0000', 'plano' => 'Mensal', 'status' => 'ativo', 'data_inicio' => today()->toDateString(), 'data_vencimento' => today()->addMonth()->toDateString()];
        $first = $this->actingAs($owner)->postJson('/api/students', $data)->assertCreated();
        $second = $this->postJson('/api/students', $data)->assertCreated();
        $this->assertSame('wendel', $first->json('credentials.username'));
        $this->assertSame('wendel2', $second->json('credentials.username'));
        $password = $first->json('credentials.temporaryPassword');
        $this->assertNotSame('123456', $password);
        $account = StudentAccount::where('aluno_id', $first->json('student.id'))->firstOrFail();
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertTrue($account->must_change_password);
        $this->getJson('/api/students/'.$account->aluno_id.'/access')->assertOk()->assertJsonMissing(['temporaryPassword' => $password])->assertJsonMissing(['password' => $account->password]);
        $this->postJson('/api/students', [...$data, 'username' => 'wendel'])->assertUnprocessable();
        $this->assertDatabaseCount('student_accounts', 2);
        $this->assertDatabaseCount('alunos', 3);
    }

    public function test_first_access_requires_password_change_and_student_cannot_access_management(): void
    {
        [, , $student] = $this->setupStudent();
        $credentials = $this->loginStudent($student, false);
        $this->getJson('/api/student/home')->assertForbidden();
        $this->getJson('/api/workouts')->assertUnauthorized();
        $this->getJson('/api/finance')->assertUnauthorized();
        $this->putJson('/api/student/password', ['current_password' => 'wrong', 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'])->assertUnprocessable();
        $this->putJson('/api/student/password', ['current_password' => $credentials['temporaryPassword'], 'password' => 'NovaSenha123', 'password_confirmation' => 'NovaSenha123'])->assertOk()->assertJsonPath('student.mustChangePassword', false);
        $this->getJson('/api/student/home')->assertOk()->assertJsonPath('plan', null);
        $this->postJson('/api/student/logout')->assertOk();
        $this->getJson('/api/student/home')->assertUnauthorized();
        $this->postJson('/api/student/login', ['username' => $credentials['username'], 'password' => $credentials['temporaryPassword']])->assertUnprocessable();
        $this->postJson('/api/student/login', ['username' => strtoupper($credentials['username']), 'password' => 'NovaSenha123'])->assertOk();
    }

    public function test_access_creation_and_reset_are_scoped_and_reset_revokes_existing_sessions(): void
    {
        [$owner, , $student] = $this->setupStudent();
        [$outsider] = $this->setupStudent('other');
        $this->actingAs($outsider)->postJson("/api/students/{$student->id}/access", ['username' => 'wendel'])->assertNotFound();
        $issued = $this->actingAs($owner)->postJson("/api/students/{$student->id}/access", ['username' => 'wendel'])->assertOk();
        $this->postJson("/api/students/{$student->id}/access", [])->assertUnprocessable();
        $account = StudentAccount::where('aluno_id', $student->id)->firstOrFail();
        $oldVersion = $account->credential_version;
        $reset = $this->postJson("/api/students/{$student->id}/access", ['reset' => true])->assertOk();
        $this->assertNotSame($issued->json('credentials.temporaryPassword'), $reset->json('credentials.temporaryPassword'));
        $account->refresh();
        $this->assertGreaterThan($oldVersion, $account->credential_version);
        $this->withSession(['student_credential_version' => $oldVersion]);
        Auth::guard('student')->login($account);
        $this->getJson('/api/student/home')->assertUnauthorized();
    }

    public function test_publication_freezes_prescription_and_replacement_preserves_old_sessions(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        $workout = $this->workout($owner, $organization);
        $publishing = app(WorkoutPublishingService::class);
        $plan = $publishing->publish($student, $workout, $owner->id);
        $this->loginStudent($student);
        $this->getJson('/api/student/home')->assertOk()->assertJsonPath('plan.days.0.exercises.0.instructions', 'Controle a descida.');
        $started = $this->postJson('/api/student/sessions', ['dayIndex' => 0])->assertOk();
        $sessionId = $started->json('session.id');
        $workout->dias->first()->exercicios->first()->update(['carga' => '40 kg']);
        $this->getJson('/api/student/home')->assertJsonPath('plan.days.0.exercises.0.load', '20 kg');
        $next = $publishing->publish($student, $workout->fresh(), $owner->id);
        $this->getJson('/api/student/home')->assertJsonPath('plan.days.0.exercises.0.load', '40 kg');
        $this->getJson("/api/student/sessions/{$sessionId}")->assertJsonPath('session.snapshot.exercises.0.load', '20 kg');
        $this->assertSame('encerrado', $plan->fresh()->status);
        $this->assertNotSame($plan->id, $next->id);
        $this->postJson('/api/student/sessions', ['dayIndex' => 0])->assertJsonPath('session.id', $sessionId);
        $this->assertDatabaseCount('student_workout_sessions', 1);
    }

    public function test_session_progress_is_restored_finish_is_idempotent_and_completed_history_is_immutable(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        app(WorkoutPublishingService::class)->publish($student, $this->workout($owner, $organization), $owner->id);
        $this->loginStudent($student);
        $id = $this->postJson('/api/student/sessions', ['dayIndex' => 0])->json('session.id');
        $progress = [['exerciseIndex' => 0, 'setIndex' => 0, 'done' => true, 'load' => '22 kg', 'repetitions' => 12]];
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => $progress])->assertOk();
        $this->getJson("/api/student/sessions/{$id}")->assertJsonPath('session.progress.0.load', '22 kg');
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => $progress, 'finish' => true])->assertJsonPath('session.status', 'completed');
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => [], 'finish' => true])->assertJsonPath('session.progress.0.load', '22 kg');
        $this->getJson('/api/student/home')->assertJsonPath('weekCompleted', 1)->assertJsonPath('activeSession', null)->assertJsonCount(1, 'history');
        $this->postJson('/api/student/sessions', ['dayIndex' => 0])->assertOk();
        $this->assertDatabaseCount('student_workout_sessions', 2);
        $this->actingAs($owner)->getJson('/api/students')->assertOk()->assertJsonPath('students.0.lastWorkout', StudentWorkoutSession::findOrFail($id)->finished_at->format('d/m/Y H:i'));
    }

    public function test_invalid_days_series_duplicates_and_empty_finish_are_rejected(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        $workout = $this->workout($owner, $organization);
        app(WorkoutPublishingService::class)->publish($student, $workout, $owner->id);
        $this->loginStudent($student);
        $this->postJson('/api/student/sessions', ['dayIndex' => 6])->assertUnprocessable();
        $id = $this->postJson('/api/student/sessions', ['dayIndex' => 0])->json('session.id');
        $item = ['exerciseIndex' => 0, 'setIndex' => 3, 'done' => true];
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => [$item]])->assertUnprocessable();
        $item['setIndex'] = 0;
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => [$item, $item]])->assertUnprocessable();
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => [], 'finish' => true])->assertUnprocessable();
        $this->patchJson("/api/student/sessions/{$id}", ['progress' => [], 'abandon' => true])->assertJsonPath('session.status', 'abandoned');
        $this->getJson('/api/student/home')->assertJsonPath('weekCompleted', 0)->assertJsonPath('activeSession', null);
    }

    public function test_student_cannot_read_or_write_other_students_sessions(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        [, , $other] = $this->setupStudent('other');
        $plan = app(WorkoutPublishingService::class)->publish($student, $this->workout($owner, $organization), $owner->id);
        $session = StudentWorkoutSession::create(['aluno_id' => $student->id, 'student_workout_plan_id' => $plan->id, 'day_index' => 0, 'snapshot' => $plan->snapshot['days'][0], 'progress' => [], 'started_at' => now()]);
        $this->loginStudent($other);
        $this->getJson('/api/student/home')->assertJsonPath('plan', null)->assertJsonPath('activeSession', null);
        $this->getJson("/api/student/sessions/{$session->id}")->assertNotFound();
        $this->patchJson("/api/student/sessions/{$session->id}", ['progress' => [], 'abandon' => true])->assertNotFound();
    }

    public function test_expired_plans_and_drafts_cannot_start_new_sessions(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        $workout = $this->workout($owner, $organization);
        $plan = app(WorkoutPublishingService::class)->publish($student, $workout, $owner->id);
        $plan->update(['ends_on' => today()->subDay()]);
        $this->loginStudent($student);
        $this->postJson('/api/student/sessions', ['dayIndex' => 0])->assertUnprocessable();
        $workout->update(['status' => 'rascunho']);
        $this->actingAs($owner)->postJson("/api/students/{$student->id}/workouts/{$workout->id}")->assertUnprocessable();
    }

    public function test_incomplete_draft_can_be_saved_but_not_published_and_duplicate_archive_restore_work(): void
    {
        [$owner] = $this->setupStudent();
        $data = ['nome' => 'Rascunho', 'objetivo' => 'Força', 'nivel' => 'iniciante', 'sessoes_semana' => 3, 'duracao_semanas' => 4, 'status' => 'rascunho', 'days' => [['name' => 'Treino A', 'exercises' => []]]];
        $id = $this->actingAs($owner)->postJson('/api/workouts', $data)->assertCreated()->json('workout.id');
        $this->putJson("/api/workouts/{$id}", [...$data, 'status' => 'ativo'])->assertUnprocessable();
        $copy = $this->postJson("/api/workouts/{$id}/duplicate")->assertCreated();
        $this->assertNotSame($id, $copy->json('workout.id'));
        $this->patchJson("/api/workouts/{$id}/status", ['status' => 'arquivado'])->assertJsonPath('workout.status', 'arquivado');
        $this->patchJson("/api/workouts/{$id}/status", ['status' => 'rascunho'])->assertJsonPath('workout.status', 'rascunho');
    }

    public function test_personalization_uses_published_snapshot_and_edit_preserves_ids_and_rejects_stale_revision(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        $workout = $this->workout($owner, $organization);
        $plan = app(WorkoutPublishingService::class)->publish($student, $workout, $owner->id);
        $workout->dias->first()->exercicios->first()->update(['carga' => '99 kg']);
        $copy = $this->actingAs($owner)->postJson("/api/students/{$student->id}/workout-plans/{$plan->id}/personalize")->assertCreated()->json('workout');
        $this->assertSame('20 kg', $copy['days'][0]['exercises'][0]['load']);
        $data = ['nome' => $copy['name'], 'objetivo' => $copy['objective'], 'nivel' => $copy['level'], 'sessoes_semana' => $copy['sessionsPerWeek'], 'duracao_semanas' => $copy['durationWeeks'], 'status' => 'ativo', 'days' => $copy['days'], 'revision' => $copy['revision'], 'studentId' => $student->id];
        $this->travel(2)->seconds();
        $updated = $this->putJson('/api/workouts/'.$copy['id'], $data)->assertOk();
        $this->assertSame($copy['days'][0]['id'], $updated->json('workout.days.0.id'));
        $this->assertSame($copy['days'][0]['exercises'][0]['id'], $updated->json('workout.days.0.exercises.0.id'));
        $this->putJson('/api/workouts/'.$copy['id'], $data)->assertConflict();
        $this->assertDatabaseCount('student_workout_plans', 2);
    }

    public function test_migration_preserves_legacy_assignments_without_generating_accounts(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        $workout = $this->workout($owner, $organization);
        $student->treinos()->attach($workout->id, ['ativo' => true]);
        $migration = require database_path('migrations/2026_10_02_000001_create_student_portal.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseCount('student_accounts', 0);
        $this->assertDatabaseCount('student_workout_plans', 1);
        $this->assertSame('20 kg', app(WorkoutPublishingService::class)->current($student->id)->snapshot['days'][0]['exercises'][0]['load']);
    }

    public function test_session_rejects_stale_progress_from_a_second_tab(): void
    {
        [$owner, $organization, $student] = $this->setupStudent();
        app(WorkoutPublishingService::class)->publish($student, $this->workout($owner, $organization), $owner->id);
        $this->loginStudent($student);
        $session = $this->postJson('/api/student/sessions', ['dayIndex' => 0])->json('session');
        $progress = [['exerciseIndex' => 0, 'setIndex' => 0, 'done' => true, 'load' => '25 kg', 'repetitions' => 10]];
        $saved = $this->patchJson('/api/student/sessions/'.$session['id'], ['progress' => $progress, 'revision' => $session['revision']])->assertOk();
        $this->assertNotSame($session['revision'], $saved->json('session.revision'));
        $this->patchJson('/api/student/sessions/'.$session['id'], ['progress' => [], 'revision' => $session['revision']])->assertConflict();
        $this->getJson('/api/student/sessions/'.$session['id'])->assertJsonPath('session.progress.0.load', '25 kg');
    }

    public function test_catalog_search_reaches_exercises_beyond_the_first_hundred_and_paginates(): void
    {
        [$owner] = $this->setupStudent();
        for ($index = 0; $index < 105; $index++) {
            Exercicio::create(['nome' => sprintf('Exercício %03d', $index), 'grupo_muscular' => 'Peito']);
        }
        Exercicio::create(['nome' => 'Z exercício específico', 'grupo_muscular' => 'Costas']);
        $this->actingAs($owner)->getJson('/api/exercises')->assertOk()->assertJsonCount(40, 'exercises')->assertJsonPath('pagination.total', 106);
        $this->getJson('/api/exercises?page=3')->assertOk()->assertJsonCount(26, 'exercises');
        $this->getJson('/api/exercises?q=espec%C3%ADfico')->assertOk()->assertJsonCount(1, 'exercises')->assertJsonPath('exercises.0.name', 'Z exercício específico');
    }
}
