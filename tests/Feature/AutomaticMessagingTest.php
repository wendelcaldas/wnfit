<?php

namespace Tests\Feature;

use App\Jobs\SendEventWhatsApp;
use App\Jobs\SendWelcomeWhatsApp;
use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Mensagem;
use App\Models\MensagemConfiguracao;
use App\Models\Organizacao;
use App\Models\StudentWorkoutPlan;
use App\Models\Treino;
use App\Models\User;
use App\Services\Messaging\AutomaticMessagingService;
use App\Services\Messaging\MessagingService;
use App\Services\PublicBookingNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomaticMessagingTest extends TestCase
{
    use RefreshDatabase;

    private Organizacao $organization;

    private Aluno $student;

    private AutomaticMessagingService $messaging;

    private int $bridgeStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(8, 0));
        Queue::fake();
        Http::fake(['bridge/events' => fn () => Http::response(['id' => 'wa-event'], $this->bridgeStatus)]);
        $this->organization = Organizacao::create(['nome_fantasia' => 'Studio', 'slug' => 'automatic-tests', 'tipo' => 'studio', 'ativa' => true]);
        $this->organization->forceFill(['modules' => ['messaging' => true]])->save();
        $this->student = Aluno::create(['organizacao_id' => $this->organization->id, 'nome' => 'Mariana', 'telefone' => '71999887766', 'status' => 'ativo']);
        config([
            'services.messaging.events_enabled' => true,
            'services.messaging.baileys.url' => 'http://bridge',
            'services.messaging.baileys.token' => 'test-token',
        ]);
        app(MessagingService::class)->settingsForOrganization($this->organization)->update([
            'event_preferences' => array_fill_keys(array_keys(MensagemConfiguracao::EVENT_DEFAULTS), true),
            'overdue_delay_days' => 3,
        ]);
        $this->messaging = app(AutomaticMessagingService::class);
    }

    private function charge(int $days = 0, array $attributes = []): Cobranca
    {
        return Cobranca::create(array_replace([
            'organizacao_id' => $this->organization->id, 'aluno_id' => $this->student->id,
            'competencia' => '11/2026', 'valor' => 150, 'vencimento' => today()->addDays($days),
            'status' => $days < 0 ? 'atrasado' : 'pendente', 'origem' => 'mensalidade',
        ], $attributes));
    }

    private function event(): Agendamento
    {
        return Agendamento::create(['organizacao_id' => $this->organization->id, 'titulo' => 'Pilates',
            'tipo' => 'individual', 'status' => 'agendado', 'inicio_em' => now()->addDay(), 'fim_em' => now()->addDay()->addHour()]);
    }

    private function send(Mensagem $message): void
    {
        (new SendEventWhatsApp($message->id))->handle($this->messaging);
    }

    public function test_internal_module_toggle_alone_releases_messages_and_revocation_stops_pending_jobs(): void
    {
        config([
            'services.messaging.welcome_driver' => 'baileys',
            // Old allowlists must neither grant nor block module-based access.
            'services.messaging.baileys.organization_ids' => [],
            'services.messaging.module_organization_slugs' => [$this->organization->slug],
        ]);
        $this->organization->forceFill(['modules' => ['messaging' => false]])->save();
        $this->charge();
        $this->assertSame(0, $this->messaging->syncBilling());
        $this->assertNull(app(MessagingService::class)->sendWelcomeMessage($this->student->fresh()));

        $internal = User::factory()->create();
        $internal->forceFill(['is_wnfit_internal' => true])->save();
        $url = '/api/internal/modules/'.$this->organization->id;
        $this->actingAs($internal)->patchJson($url, ['module' => 'messaging', 'enabled' => true])->assertOk();
        $this->assertSame(1, $this->messaging->syncBilling());
        $welcome = app(MessagingService::class)->sendWelcomeMessage($this->student->fresh());
        $this->assertNotNull($welcome);
        Queue::assertPushed(SendWelcomeWhatsApp::class, 1);
        Queue::assertPushed(SendEventWhatsApp::class, 1);

        $owner = User::factory()->create();
        $this->organization->usuarios()->attach($owner->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        $this->actingAs($owner)->getJson('/api/organization/messaging')->assertOk()
            ->assertJsonPath('settings.eventsAutomatic', true)->assertJsonPath('settings.welcomeAutomatic', true);
        $this->patchJson($url, ['module' => 'messaging', 'enabled' => false])->assertForbidden();
        $this->actingAs($internal)->patchJson($url, ['module' => 'messaging', 'enabled' => false])->assertOk();
        (new SendWelcomeWhatsApp($welcome->id))->handle();
        $eventMessage = Mensagem::where('tipo', 'vencimento_hoje')->firstOrFail();
        $this->send($eventMessage);
        $this->assertSame('falhou', $welcome->fresh()->status);
        $this->assertSame('cancelado', $eventMessage->fresh()->status);
        $this->assertSame(0, $this->messaging->syncBilling());
        Http::assertNothingSent();
    }

    public function test_billing_dates_and_overdue_delay_queue_once_and_exclude_event_paid_and_other_dates(): void
    {
        $this->charge(2);
        $this->charge();
        $this->charge(-3);
        $this->charge(-1);
        $this->charge(1);
        $this->charge(0, ['origem' => 'evento']);
        $this->charge(0, ['status' => 'pago', 'pago_em' => now()]);
        $this->assertSame(3, $this->messaging->syncBilling());
        $this->assertSame(0, $this->messaging->syncBilling());
        Queue::assertPushed(SendEventWhatsApp::class, 3);
        $this->assertEqualsCanonicalizing(['vencimento_proximo', 'vencimento_hoje', 'cobranca_atrasada'], Mensagem::pluck('tipo')->all());
        Http::assertNothingSent();
    }

    public function test_pending_charge_is_cancelled_if_paid_before_worker(): void
    {
        $charge = $this->charge();
        $this->messaging->syncBilling();
        $charge->update(['status' => 'pago']);
        $this->send($message = Mensagem::firstOrFail());
        $this->assertSame('cancelado', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_event_job_sends_once_and_preserves_source(): void
    {
        $this->charge();
        $this->messaging->syncBilling();
        $message = Mensagem::firstOrFail();
        $this->send($message);
        $this->send($message);
        $this->assertSame('enviado', $message->fresh()->status);
        $this->assertSame('wa-event', $message->fresh()->provider_message_id);
        $this->assertArrayHasKey('due_date', $message->fresh()->payload);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['key'] === $message->event_key);
    }

    public function test_global_switch_module_active_organization_and_preference_are_required(): void
    {
        $this->charge();
        foreach ([
            ['services.messaging.events_enabled' => false],
        ] as $override) {
            $previous = [];
            foreach ($override as $key => $value) {
                $previous[$key] = config($key);
            }
            config($override);
            $this->assertSame(0, $this->messaging->syncBilling());
            config($previous);
        }
        $this->organization->forceFill(['modules' => ['messaging' => false]])->save();
        $this->assertSame(0, $this->messaging->syncBilling());
        $this->organization->forceFill(['modules' => ['messaging' => true], 'ativa' => false])->save();
        $this->assertSame(0, $this->messaging->syncBilling());
        $this->organization->update(['ativa' => true]);
        app(MessagingService::class)->settingsForOrganization($this->organization)->update(['event_preferences' => ['vencimento_hoje' => false]]);
        $this->assertSame(0, $this->messaging->syncBilling());
        $this->assertDatabaseCount('mensagens', 0);
    }

    public function test_worker_rechecks_changed_phone(): void
    {
        $this->charge();
        $this->messaging->syncBilling();
        $this->student->update(['telefone' => '71988776655']);
        $this->send($message = Mensagem::firstOrFail());
        $this->assertSame('cancelado', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_workout_assignment_is_deduplicated_and_superseded_plan_is_cancelled(): void
    {
        $plan = StudentWorkoutPlan::create(['aluno_id' => $this->student->id, 'status' => 'ativo', 'snapshot' => ['name' => 'Força'], 'starts_on' => today(), 'ends_on' => today()->addWeeks(4)]);
        $message = $this->messaging->workout($plan);
        $this->assertSame($message->id, $this->messaging->workout($plan)->id);
        $plan->update(['status' => 'encerrado']);
        $this->send($message);
        $this->assertSame('cancelado', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_student_booking_hook_queues_confirmation_and_removal_cancels_it(): void
    {
        $event = $this->event();
        $event->alunos()->attach($this->student->id);
        app(PublicBookingNotificationService::class)->studentBooked($event, $this->student);
        $message = Mensagem::firstOrFail();
        $this->assertSame('agendamento_confirmado', $message->tipo);
        $event->alunos()->detach($this->student->id);
        $this->send($message);
        $this->assertSame('cancelado', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_public_booking_queues_without_student_and_rechecks_rescheduled_event(): void
    {
        $event = $this->event();
        $booking = $event->publicBooking()->create(['organizacao_id' => $this->organization->id,
            'request_id' => (string) Str::uuid(), 'reference' => 'TEST-BOOKING',
            'name' => 'Visitante', 'phone' => '71999887766']);
        app(PublicBookingNotificationService::class)->created($event, $booking, 'Pilates');
        $message = Mensagem::firstOrFail();
        $this->assertNull($message->aluno_id);
        $this->assertTrue($this->messaging->valid($message));
        $event->update(['inicio_em' => now()->addDays(2)]);
        $this->send($message);
        $this->assertSame('cancelado', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_manager_appointment_and_workout_publication_enqueue_through_real_routes(): void
    {
        $owner = User::factory()->create();
        $this->organization->usuarios()->attach($owner->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        $this->actingAs($owner)->postJson('/api/schedule', [
            'title' => 'Pilates', 'type' => 'individual', 'status' => 'agendado',
            'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
            'instructor_id' => $owner->id, 'modality' => 'presencial', 'participant_ids' => [$this->student->id],
        ])->assertCreated();
        $workout = Treino::create(['organizacao_id' => $this->organization->id, 'nome' => 'Força',
            'objetivo' => 'Força', 'status' => 'ativo', 'duracao_semanas' => 4]);
        $this->postJson('/api/students/'.$this->student->id.'/workouts/'.$workout->id)->assertOk();
        $this->assertDatabaseHas('mensagens', ['tipo' => 'agendamento_confirmado', 'status' => 'pendente']);
        $this->assertDatabaseHas('mensagens', ['tipo' => 'treino_atualizado', 'status' => 'pendente']);
        Queue::assertPushed(SendEventWhatsApp::class, 2);
        foreach (Mensagem::all() as $message) {
            $this->send($message);
            $this->assertSame('enviado', $message->fresh()->status);
        }
    }

    public function test_disabling_preference_cancels_pending_and_invalid_phone_does_not_break_billing(): void
    {
        $this->charge();
        $this->messaging->syncBilling();
        app(MessagingService::class)->settingsForOrganization($this->organization)->update(['event_preferences' => ['vencimento_hoje' => false]]);
        $this->send($message = Mensagem::firstOrFail());
        $this->assertSame('cancelado', $message->fresh()->status);
        $this->student->update(['telefone' => 'invalid']);
        $this->assertSame(0, $this->messaging->syncBilling());
        Http::assertNothingSent();
    }

    public function test_ambiguous_result_stops_retry_and_unavailable_bridge_keeps_pending(): void
    {
        $this->charge();
        $this->messaging->syncBilling();
        $message = Mensagem::firstOrFail();
        $this->bridgeStatus = 503;
        try {
            $this->send($message);
            $this->fail('Expected retry');
        } catch (\RuntimeException) {
            $this->assertSame('pendente', $message->fresh()->status);
        }
        $this->bridgeStatus = 409;
        $this->send($message);
        $this->assertSame('incerto', $message->fresh()->status);
        $this->send($message);
        Http::assertSentCount(2);
    }
}
