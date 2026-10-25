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
        $plan = StudentWorkoutPlan::create(['aluno_id' => $student->id, 'snapshot' => [], 'starts_on' => today()]);
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

    public function test_notification_failure_rolls_back_completion_for_a_safe_retry(): void
    {
        $f = $this->fixture();
        $this->mock(WorkoutNotificationService::class)->shouldReceive('completed')->once()->andThrow(new RuntimeException('Simulated persistence failure'));
        $this->finish($f)->assertStatus(500);
        $this->assertSame('in_progress', $f['session']->fresh()->status);
        $this->assertDatabaseCount('staff_notifications', 0);
    }
}
