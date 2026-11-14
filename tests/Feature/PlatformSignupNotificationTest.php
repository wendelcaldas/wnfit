<?php

namespace Tests\Feature;

use App\Jobs\SendPlatformSignupWhatsApp;
use App\Models\PlatformSignupNotification;
use App\Services\Messaging\MessagingService;
use App\Services\Messaging\PlatformSignupNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    private int $bridgeStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::fake(['bridge/platform-notifications' => fn () => Http::response(['id' => 'signup-id'], $this->bridgeStatus)]);
        config([
            'services.messaging.signup_alerts.enabled' => true,
            'services.messaging.signup_alerts.recipient' => '+5571996651964',
            'services.messaging.baileys.url' => 'http://bridge',
            'services.messaging.baileys.token' => 'test-token',
        ]);
    }

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/register', array_replace([
            'name' => 'Maria Gestora', 'email' => 'maria@example.com', 'company' => 'Studio Movimento',
            'phone' => '71999991234', 'password' => 'Senha123!', 'password_confirmation' => 'Senha123!', 'terms' => true,
            'onboarding_profile' => ['student_range' => '21_50', 'service_mode' => 'hybrid', 'team' => 'team', 'priorities' => ['workouts', 'finance']],
        ], $overrides));
    }

    private function send(PlatformSignupNotification $notification): void
    {
        (new SendPlatformSignupWhatsApp($notification->id))->handle(app(MessagingService::class));
    }

    public function test_completed_signup_alerts_operator_once_even_with_messaging_module_disabled(): void
    {
        $response = $this->register()->assertCreated()->assertJsonPath('organization.modules.messaging', false);
        $notification = PlatformSignupNotification::firstOrFail();
        $this->assertSame($response->json('organization.id'), $notification->organizacao_id);
        $this->assertSame('+5571996651964', $notification->recipient);
        foreach (['Studio Movimento', 'Maria Gestora', 'maria@example.com', '71999991234', '21 a 50 alunos', 'Híbrido', 'Possui equipe', 'Treinos, Financeiro', '(Bahia)', '/modulos'] as $detail) {
            $this->assertStringContainsString($detail, $notification->body);
        }
        $this->assertStringNotContainsString('Senha123!', $notification->body);
        $this->assertStringNotContainsString('password', $notification->body);
        Queue::assertPushed(SendPlatformSignupWhatsApp::class, 1);
        Queue::assertPushed(SendPlatformSignupWhatsApp::class, fn ($job) => $job->queue === 'whatsapp' && $job->connection === 'database');
        $this->assertSame(0, app(PlatformSignupNotificationService::class)->dispatchPending());
        $this->send($notification);
        $this->send($notification);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['to'] === '+5571996651964' && $request['key'] === 'platform:signup:'.$notification->organizacao_id);
        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertSame('signup-id', $notification->fresh()->provider_message_id);
    }

    public function test_invalid_or_duplicate_signup_does_not_generate_another_alert(): void
    {
        $this->register(['terms' => false])->assertUnprocessable();
        $this->assertDatabaseCount('platform_signup_notifications', 0);
        $this->register()->assertCreated();
        $this->register()->assertUnprocessable();
        $this->assertDatabaseCount('platform_signup_notifications', 1);
        Queue::assertPushed(SendPlatformSignupWhatsApp::class, 1);
        Http::assertNothingSent();
    }

    public function test_disabled_channel_does_not_block_registration_or_backfill_old_signups(): void
    {
        config(['services.messaging.signup_alerts.enabled' => false]);
        $this->register()->assertCreated();
        $this->assertDatabaseCount('platform_signup_notifications', 0);
        config(['services.messaging.signup_alerts.enabled' => true]);
        $this->assertSame(0, app(PlatformSignupNotificationService::class)->dispatchPending());
        Queue::assertNothingPushed();
    }

    public function test_queue_failure_keeps_signup_successful_and_scheduler_recovers_it(): void
    {
        $originalQueue = Queue::getFacadeRoot();
        Queue::shouldReceive('connection')->with('database')->andThrow(new \RuntimeException('Queue unavailable'));
        $this->register()->assertCreated();
        $notification = PlatformSignupNotification::firstOrFail();
        $this->assertNull($notification->queued_at);
        $this->assertSame('pending', $notification->status);
        Queue::swap($originalQueue);
        $this->artisan('platform-signups:dispatch')->assertSuccessful();
        Queue::assertPushed(SendPlatformSignupWhatsApp::class, 1);
    }

    public function test_changing_operator_number_cancels_pending_alert_instead_of_redirecting_it(): void
    {
        $this->register()->assertCreated();
        $notification = PlatformSignupNotification::firstOrFail();
        config(['services.messaging.signup_alerts.recipient' => '+5571999887766']);
        $this->send($notification);
        $this->assertSame('cancelled', $notification->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_disconnected_bridge_retries_and_ambiguous_send_is_not_repeated(): void
    {
        $this->register()->assertCreated();
        $notification = PlatformSignupNotification::firstOrFail();
        $this->bridgeStatus = 503;
        try {
            $this->send($notification);
            $this->fail('Expected retry');
        } catch (\RuntimeException) {
            $this->assertSame('pending', $notification->fresh()->status);
        }
        $this->bridgeStatus = 409;
        $this->send($notification);
        $this->send($notification);
        $this->assertSame('uncertain', $notification->fresh()->status);
        Http::assertSentCount(2);
    }
}
