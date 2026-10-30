<?php

namespace Tests\Feature;

use App\Jobs\SendStaffWhatsApp;
use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StaffWhatsAppDelivery;
use App\Models\StudentWorkoutPlan;
use App\Models\StudentWorkoutSession;
use App\Models\User;
use App\Services\Messaging\StaffWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StaffWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Queue::fake();
        $organization = Organizacao::create(['nome_fantasia' => 'Piloto', 'slug' => 'piloto', 'tipo' => 'studio', 'ativa' => true, 'telefone_contato' => '71996651964']);
        $manager = User::factory()->create();
        $organization->usuarios()->attach($manager, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Ana', 'status' => 'ativo']);
        $plan = StudentWorkoutPlan::create(['aluno_id' => $student->id, 'snapshot' => [], 'starts_on' => today(), 'ends_on' => today()->addMonth()]);
        $session = StudentWorkoutSession::create(['aluno_id' => $student->id, 'student_workout_plan_id' => $plan->id, 'day_index' => 0, 'snapshot' => [], 'progress' => [], 'started_at' => now()]);
        config([
            'services.messaging.staff_whatsapp.targets' => [$organization->id => true],
            'services.messaging.staff_whatsapp.since' => now()->subMinute()->toISOString(),
            'services.messaging.baileys.url' => 'http://bridge',
            'services.messaging.baileys.token' => 'test-token',
            'app.url' => 'https://wnfit.com.br',
        ]);

        return ['organizacao_id' => $organization->id, 'user_id' => $manager->id,
            'student_workout_session_id' => $session->id, 'student_workout_plan_id' => $plan->id,
            'type' => 'workout_completed', 'data' => ['studentId' => $student->id, 'studentName' => 'Ana', 'workoutName' => 'Treino A']];
    }

    public function test_multiple_managers_and_repeated_processing_enqueue_one_delivery(): void
    {
        $attributes = $this->fixture();
        $first = StaffNotification::create($attributes);
        $admin = User::factory()->create();
        Organizacao::findOrFail($attributes['organizacao_id'])->usuarios()->attach($admin, ['papel' => 'administrador', 'status' => 'ativo']);
        StaffNotification::create([...$attributes, 'user_id' => $admin->id]);
        app(StaffWhatsAppService::class)->enqueue($first);
        $this->assertDatabaseCount('staff_whatsapp_deliveries', 1);
        Queue::assertPushed(SendStaffWhatsApp::class, 1);
        $delivery = StaffWhatsAppDelivery::firstOrFail();
        $this->assertStringContainsString('Ana finalizou Treino A', $delivery->body);
        $this->assertStringContainsString('https://wnfit.com.br/alunos/', $delivery->body);
    }

    public function test_disabled_pilot_teacher_and_old_deadlines_do_not_send(): void
    {
        $attributes = $this->fixture();
        config(['services.messaging.staff_whatsapp.targets' => []]);
        StaffNotification::create($attributes);
        config(['services.messaging.staff_whatsapp.targets' => [$attributes['organizacao_id'] => '+5571996651964']]);
        Organizacao::findOrFail($attributes['organizacao_id'])->usuarios()->updateExistingPivot($attributes['user_id'], ['papel' => 'professor']);
        app(StaffWhatsAppService::class)->enqueue(StaffNotification::firstOrFail());
        Organizacao::findOrFail($attributes['organizacao_id'])->usuarios()->updateExistingPivot($attributes['user_id'], ['papel' => 'proprietario']);
        StaffNotification::create([...$attributes, 'type' => 'workout_plan_expired', 'data' => [...$attributes['data'], 'endsOn' => today()->subMonth()->toDateString()]]);
        StaffNotification::create([...$attributes, 'type' => 'workout_plan_ends_today']);
        $this->assertDatabaseCount('staff_whatsapp_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_completion_sends_once_and_bridge_uses_persistent_event_key(): void
    {
        $this->travelTo(today()->setHour(9));
        $attributes = $this->fixture();
        StaffNotification::create($attributes);
        $delivery = StaffWhatsAppDelivery::firstOrFail();
        Http::fake(['bridge/*' => Http::response(['id' => 'wa-123'])]);
        $job = new SendStaffWhatsApp($delivery->id);
        $job->handle(app(StaffWhatsAppService::class));
        $job->handle(app(StaffWhatsAppService::class));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'http://bridge/staff-notifications' && $request['key'] === $delivery->event_key);
        $this->assertSame('sent', $delivery->fresh()->status);
    }

    public function test_ambiguous_send_is_not_retried_and_disconnection_is_retryable(): void
    {
        StaffNotification::create($this->fixture());
        $delivery = StaffWhatsAppDelivery::firstOrFail();
        $job = new SendStaffWhatsApp($delivery->id);
        Http::fake(['bridge/*' => Http::sequence()->push([], 503)->push([], 409)]);
        try {
            $job->handle(app(StaffWhatsAppService::class));
            $this->fail('Disconnected bridge must retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('pending', $delivery->fresh()->status);
        }
        $job->handle(app(StaffWhatsAppService::class));
        $job->handle(app(StaffWhatsAppService::class));
        $this->assertSame('uncertain', $delivery->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_disabling_pilot_cancels_already_queued_delivery(): void
    {
        StaffNotification::create($this->fixture());
        $delivery = StaffWhatsAppDelivery::firstOrFail();
        config(['services.messaging.staff_whatsapp.targets' => []]);
        Http::fake();
        (new SendStaffWhatsApp($delivery->id))->handle(app(StaffWhatsAppService::class));
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_expiration_stays_in_system_and_old_queued_expiration_is_cancelled(): void
    {
        $this->travelTo(today()->setTime(0, 5));
        $attributes = $this->fixture();
        StaffNotification::create([...$attributes, 'type' => 'workout_plan_expired', 'data' => [...$attributes['data'], 'endsOn' => today()->toDateString()]]);
        $this->assertDatabaseCount('staff_notifications', 1);
        $this->assertDatabaseCount('staff_whatsapp_deliveries', 0);
        Queue::assertNothingPushed();
        $notification = StaffNotification::firstOrFail();
        $delivery = StaffWhatsAppDelivery::create(['organizacao_id' => $notification->organizacao_id,
            'staff_notification_id' => $notification->id, 'event_key' => 'staff:'.$notification->organizacao_id.':workout_plan_expired:1',
            'recipient' => '+5571996651964', 'body' => 'Aviso antigo']);
        Http::fake();
        (new SendStaffWhatsApp($delivery->id))->handle(app(StaffWhatsAppService::class));
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Http::assertNothingSent();
        StaffNotification::create($attributes);
        Queue::assertPushed(SendStaffWhatsApp::class, fn ($job) => $job->delay === null);
    }

    public function test_contact_changes_affect_future_messages_and_cancel_old_destination(): void
    {
        $attributes = $this->fixture();
        StaffNotification::create($attributes);
        $delivery = StaffWhatsAppDelivery::firstOrFail();
        Organizacao::findOrFail($attributes['organizacao_id'])->update(['telefone_contato' => '(71) 99988-7766']);
        $service = app(StaffWhatsAppService::class);
        $this->assertSame('+5571999887766', $service->recipient($attributes['organizacao_id']));
        Http::fake();
        (new SendStaffWhatsApp($delivery->id))->handle($service);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        Http::assertNothingSent();
        Organizacao::findOrFail($attributes['organizacao_id'])->update(['telefone_contato' => null]);
        $this->assertNull($service->recipient($attributes['organizacao_id']));
    }
}
