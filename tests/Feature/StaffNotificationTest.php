<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StudentAccount;
use App\Models\StudentWorkoutPlan;
use App\Models\StudentWorkoutSession;
use App\Models\User;
use App\Services\WorkoutNotificationService;
use App\Services\WorkoutPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Tests\TestCase;

class StaffNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function member(Organizacao $organization, string $role, string $name, string $status = 'ativo'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $organization->usuarios()->attach($user->id, ['papel' => $role, 'status' => $status]);

        return $user;
    }

    private function fixture(string $slug = 'notificacoes'): array
    {
        $organization = Organizacao::create(['nome_fantasia' => 'Studio', 'slug' => $slug, 'tipo' => 'studio', 'ativa' => true]);
        $owner = $this->member($organization, 'proprietario', 'Gestor');
        $teacher = $this->member($organization, 'professor', 'Professor Responsável');
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno de teste', 'treinador' => $teacher->name, 'status' => 'ativo']);
        $plan = StudentWorkoutPlan::create(['aluno_id' => $student->id, 'snapshot' => [], 'starts_on' => today(), 'ends_on' => today()->addWeeks(4)]);
        $session = StudentWorkoutSession::create([
            'aluno_id' => $student->id, 'student_workout_plan_id' => $plan->id, 'day_index' => 0,
            'snapshot' => ['name' => 'Treino A', 'planName' => 'Programa ABC', 'focus' => 'Peito', 'exercises' => [['name' => 'Supino', 'sets' => 2]]],
            'progress' => [], 'started_at' => now()->subMinutes(38),
        ]);
        $account = StudentAccount::create(['aluno_id' => $student->id, 'username' => 'aluno-'.$slug, 'password' => 'Senha123!', 'must_change_password' => false, 'credential_version' => 1]);

        return compact('organization', 'owner', 'teacher', 'student', 'session', 'account');
    }

    private function finish(array $fixture, array $extra = [])
    {
        return $this->actingAs($fixture['account'], 'student')->withSession(['student_credential_version' => 1])
            ->patchJson('/api/student/sessions/'.$fixture['session']->id, [
                'progress' => [['exerciseIndex' => 0, 'setIndex' => 0, 'done' => true]], 'finish' => true, ...$extra,
            ]);
    }

    public function test_completion_notifies_only_active_owner_admin_and_assigned_teacher_once(): void
    {
        $this->freezeTime();
        $f = $this->fixture();
        $admin = $this->member($f['organization'], 'administrador', 'Administradora');
        $this->member($f['organization'], 'professor', 'Outro professor');
        $this->member($f['organization'], 'atendimento', 'Recepção');
        $this->member($f['organization'], 'administrador', 'Inativo', 'inativo');
        $other = $this->fixture('outro');
        $this->finish($f)->assertOk()->assertJsonPath('session.status', 'completed');
        $this->assertEqualsCanonicalizing([$f['owner']->id, $f['teacher']->id, $admin->id], StaffNotification::pluck('user_id')->all());
        $this->assertDatabaseMissing('staff_notifications', ['organizacao_id' => $other['organization']->id]);
        $notification = StaffNotification::firstOrFail();
        $this->assertSame(0, $notification->data['completedExercises']);
        $this->assertSame(1, $notification->data['completedSets']);
        $this->assertSame(2, $notification->data['totalSets']);
        $this->assertSame(2280, $notification->data['durationSeconds']);
        $this->assertArrayNotHasKey('progress', $notification->data);
        $this->finish($f, ['progress' => []])->assertOk();
        $this->assertDatabaseCount('staff_notifications', 3);
    }

    public function test_abandon_progress_and_invalid_finish_do_not_notify(): void
    {
        $f = $this->fixture();
        $this->finish($f, ['finish' => false])->assertOk();
        $this->assertDatabaseCount('staff_notifications', 0);
        $this->finish($f, ['progress' => []])->assertUnprocessable();
        $this->assertDatabaseCount('staff_notifications', 0);
        $this->finish($f, ['abandon' => true])->assertOk()->assertJsonPath('session.status', 'abandoned');
        $this->assertDatabaseCount('staff_notifications', 0);
    }

    public function test_ambiguous_or_missing_legacy_teacher_does_not_guess_a_recipient(): void
    {
        $f = $this->fixture();
        $this->member($f['organization'], 'professor', $f['teacher']->name);
        $this->finish($f)->assertOk();
        $this->assertDatabaseCount('staff_notifications', 1);
        $this->assertSame($f['owner']->id, StaffNotification::firstOrFail()->user_id);
        $other = $this->fixture('sem-professor');
        $other['student']->update(['treinador' => null]);
        $this->finish($other)->assertOk();
        $this->assertDatabaseCount('staff_notifications', 2);
    }

    public function test_notifications_are_private_and_read_state_is_independent_and_persistent(): void
    {
        $f = $this->fixture();
        $this->finish($f)->assertOk();
        $ownerNotification = StaffNotification::where('user_id', $f['owner']->id)->firstOrFail();
        $teacherNotification = StaffNotification::where('user_id', $f['teacher']->id)->firstOrFail();
        $this->actingAs($f['owner'], 'web')->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'notifications')->assertJsonPath('unreadCount', 1);
        $this->patchJson('/api/notifications/'.$teacherNotification->id.'/read')->assertNotFound();
        $this->patchJson('/api/notifications/'.$ownerNotification->id.'/read')->assertOk()->assertJsonPath('unreadCount', 0);
        $readAt = $ownerNotification->fresh()->read_at->toISOString();
        $this->travel(1)->minutes();
        $this->patchJson('/api/notifications/'.$ownerNotification->id.'/read')->assertOk()->assertJsonPath('notification.readAt', $readAt);
        $this->getJson('/api/notifications')->assertJsonPath('unreadCount', 0)->assertJsonCount(1, 'notifications');
        $this->actingAs($f['teacher'], 'web')->getJson('/api/notifications')->assertJsonPath('unreadCount', 1);
        $other = $this->fixture('outra-conta');
        $this->actingAs($other['owner'], 'web')->getJson('/api/notifications')->assertJsonCount(0, 'notifications')->assertJsonPath('unreadCount', 0);
        $this->patchJson('/api/notifications/'.$ownerNotification->id.'/read')->assertNotFound();
    }

    public function test_removed_members_and_students_cannot_read_staff_notifications(): void
    {
        $f = $this->fixture();
        $this->finish($f)->assertOk();
        Auth::shouldUse('web');
        $this->getJson('/api/notifications')->assertUnauthorized();
        $f['organization']->usuarios()->updateExistingPivot($f['owner']->id, ['status' => 'inativo']);
        $this->actingAs($f['owner'], 'web')->getJson('/api/notifications')->assertForbidden();
    }

    public function test_cursor_pagination_and_unread_count_cover_the_entire_inbox(): void
    {
        $f = $this->fixture();
        for ($i = 0; $i < 23; $i++) {
            $session = $f['session']->replicate();
            $session->save();
            StaffNotification::create(['organizacao_id' => $f['organization']->id, 'user_id' => $f['owner']->id, 'student_workout_session_id' => $session->id, 'type' => 'workout_completed', 'data' => []]);
        }
        $first = $this->actingAs($f['owner'], 'web')->getJson('/api/notifications')->assertOk()->assertJsonCount(20, 'notifications')->assertJsonPath('unreadCount', 23);
        $second = $this->getJson('/api/notifications?before='.$first->json('nextCursor'))->assertOk()->assertJsonCount(3, 'notifications')->assertJsonPath('nextCursor', null)->assertJsonPath('unreadCount', 23);
        $this->assertEmpty(array_intersect(array_column($first->json('notifications'), 'id'), array_column($second->json('notifications'), 'id')));
    }

    public function test_deadline_alerts_appear_in_the_inbox_once_for_the_responsible_staff_only(): void
    {
        $f = $this->fixture();
        $admin = $this->member($f['organization'], 'administrador', 'Administradora');
        $otherTeacher = $this->member($f['organization'], 'professor', 'Professor sem vínculo');
        $this->member($f['organization'], 'professor', 'Inativo', 'inativo');
        $plan = StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id);
        $plan->update(['ends_on' => today(), 'snapshot' => ['name' => 'Programa ABC', 'durationWeeks' => 4]]);
        $this->actingAs($f['owner'], 'web')->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'notifications');
        $this->artisan('workouts:notify-deadlines')->assertSuccessful();
        $first = $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(1, 'notifications')->assertJsonPath('unreadCount', 1)
            ->assertJsonPath('notifications.0.type', 'workout_plan_ends_today')
            ->assertJsonPath('notifications.0.data.endsOn', today()->toDateString())
            ->assertJsonPath('notifications.0.data.href', '/alunos/'.$f['student']->id.'?tab=workouts');
        $this->assertEqualsCanonicalizing([$f['owner']->id, $f['teacher']->id, $admin->id], StaffNotification::pluck('user_id')->all());
        $this->assertNull(StaffNotification::firstOrFail()->student_workout_session_id);
        $this->assertSame($plan->id, StaffNotification::firstOrFail()->student_workout_plan_id);
        $this->patchJson('/api/notifications/'.$first->json('notifications.0.id').'/read')->assertOk()->assertJsonPath('unreadCount', 0);
        $this->getJson('/api/notifications')->assertJsonCount(1, 'notifications')->assertJsonPath('unreadCount', 0);
        $this->actingAs($f['teacher'], 'web')->getJson('/api/notifications')->assertJsonPath('unreadCount', 1);
        $this->actingAs($otherTeacher, 'web')->getJson('/api/notifications')->assertJsonCount(0, 'notifications');
        $other = $this->fixture('deadline-other');
        $this->actingAs($other['owner'], 'web')->getJson('/api/notifications')->assertJsonCount(0, 'notifications');
        $this->patchJson('/api/notifications/'.$first->json('notifications.0.id').'/read')->assertNotFound();
        $this->assertDatabaseCount('staff_notifications', 3);
    }

    public function test_deadline_transition_creates_one_expired_alert_without_daily_duplicates_or_resetting_read_state(): void
    {
        $this->freezeTime();
        $f = $this->fixture();
        $plan = StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id);
        $plan->update(['ends_on' => today()]);
        $service = app(WorkoutNotificationService::class);
        $this->assertSame(2, $service->syncDeadlines($f['organization']->id));
        $due = StaffNotification::where('user_id', $f['owner']->id)->firstOrFail();
        $due->update(['read_at' => now()]);
        $readAt = $due->fresh()->read_at->toISOString();
        $this->travel(1)->days();
        $this->assertSame(2, $service->syncDeadlines($f['organization']->id));
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $this->travel(2)->days();
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $this->assertDatabaseCount('staff_notifications', 4);
        $this->assertSame(2, StaffNotification::where('type', 'workout_plan_expired')->count());
        $this->assertSame($readAt, $due->fresh()->read_at->toISOString());
        $this->travelBack();
    }

    public function test_legacy_deadlines_use_the_snapshot_and_ambiguous_teachers_are_not_guessed(): void
    {
        $f = $this->fixture();
        $plan = StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id);
        $service = app(WorkoutNotificationService::class);
        $plan->update(['ends_on' => null, 'starts_on' => today()->subWeeks(5), 'snapshot' => ['durationWeeks' => 4]]);
        $this->member($f['organization'], 'professor', $f['teacher']->name);
        $this->assertSame(1, $service->syncDeadlines($f['organization']->id));
        $notification = StaffNotification::firstOrFail();
        $this->assertSame($f['owner']->id, $notification->user_id);
        $this->assertSame('workout_plan_expired', $notification->type);
        $this->assertSame(today()->subWeek()->toDateString(), $notification->data['endsOn']);
        $this->assertArrayNotHasKey('exercises', $notification->data);
        $this->assertArrayNotHasKey('load', $notification->data);
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
    }

    public function test_renewed_superseded_future_and_unresolvable_plans_do_not_create_stale_deadline_alerts(): void
    {
        $f = $this->fixture();
        $plan = StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id);
        $service = app(WorkoutNotificationService::class);
        $plan->update(['ends_on' => null, 'snapshot' => []]);
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $plan->update(['ends_on' => today()->addDay()]);
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $plan->update(['ends_on' => today()->subDay(), 'starts_on' => today()->addDay()]);
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $plan->update(['starts_on' => today(), 'snapshot' => ['name' => 'Ficha', 'durationWeeks' => 4]]);
        app(WorkoutPublishingService::class)->renew($f['student'], $plan, $f['owner']->id);
        $plan->update(['status' => 'ativo']);
        $this->assertSame(0, $service->syncDeadlines($f['organization']->id));
        $this->assertDatabaseCount('staff_notifications', 0);
    }

    public function test_deadline_command_is_idempotent_and_ignores_inactive_organizations(): void
    {
        $f = $this->fixture();
        StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id)->update(['ends_on' => today()->subDay()]);
        $f['organization']->update(['ativa' => false]);
        $this->artisan('workouts:notify-deadlines')->expectsOutput('0 avisos de vigência de fichas criados.')->assertSuccessful();
        $f['organization']->update(['ativa' => true]);
        $this->artisan('workouts:notify-deadlines')->expectsOutput('2 avisos de vigência de fichas criados.')->assertSuccessful();
        $this->artisan('workouts:notify-deadlines')->expectsOutput('0 avisos de vigência de fichas criados.')->assertSuccessful();
        $this->assertDatabaseCount('staff_notifications', 2);
    }

    public function test_deadline_migration_round_trip_preserves_existing_completion_notifications(): void
    {
        $f = $this->fixture();
        $this->finish($f)->assertOk();
        $originalIds = StaffNotification::pluck('id')->all();
        StudentWorkoutPlan::findOrFail($f['session']->student_workout_plan_id)->update(['ends_on' => today()]);
        app(WorkoutNotificationService::class)->syncDeadlines($f['organization']->id);
        $this->assertDatabaseCount('staff_notifications', 4);
        $migration = require database_path('migrations/2026_10_27_000001_add_workout_plan_deadline_notifications.php');
        $migration->down();
        $this->assertEqualsCanonicalizing($originalIds, StaffNotification::pluck('id')->all());
        $migration->up();
        $this->assertEqualsCanonicalizing($originalIds, StaffNotification::pluck('id')->all());
    }

    public function test_notification_failure_rolls_back_completion_for_a_safe_retry(): void
    {
        $f = $this->fixture();
        $this->mock(WorkoutNotificationService::class)->shouldReceive('completed')->once()->andThrow(new RuntimeException('Simulated persistence failure'));
        $this->finish($f)->assertStatus(500);
        $this->assertSame('in_progress', $f['session']->fresh()->status);
        $this->assertDatabaseCount('staff_notifications', 0);
    }
}
