<?php

namespace Tests\Feature;

use App\Events\StaffNotificationChanged;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StaffNotificationRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $u = User::factory()->create();
        $o = Organizacao::create(['nome_fantasia' => 'Realtime', 'slug' => 'realtime', 'tipo' => 'academia', 'ativa' => true]);
        $o->usuarios()->attach($u->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$u, $o];
    }

    private function notification($u, $o): StaffNotification
    {
        return StaffNotification::create(['organizacao_id' => $o->id, 'user_id' => $u->id, 'type' => 'public_booking_created', 'data' => ['guestName' => 'Pessoa']]);
    }

    public function test_channel_authorization_is_private_and_requires_active_staff_membership(): void
    {
        [$u,$o] = $this->fixture();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key', 'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        require base_path('routes/channels.php');
        $p = ['socket_id' => '123.456', 'channel_name' => 'private-staff.org.'.$o->id.'.user.'.$u->id];
        $this->postJson('/broadcasting/auth', $p)->assertUnauthorized();
        $this->actingAs($u)->postJson('/broadcasting/auth', $p)->assertOk()->assertJsonStructure(['auth']);
        $other = User::factory()->create();
        $p['channel_name'] = 'private-staff.org.'.$o->id.'.user.'.$other->id;
        $this->postJson('/broadcasting/auth', $p)->assertForbidden();
        $p['channel_name'] = 'private-staff.org.99999.user.'.$u->id;
        $this->postJson('/broadcasting/auth', $p)->assertForbidden();
        $p['channel_name'] = 'private-staff.org.'.$o->id.'.user.'.$u->id;
        $o->usuarios()->updateExistingPivot($u->id, ['status' => 'inativo']);
        $this->postJson('/broadcasting/auth', $p)->assertForbidden();
    }

    public function test_created_and_read_events_wait_for_commit_and_are_not_duplicated(): void
    {
        [$u,$o] = $this->fixture();
        Event::fake([StaffNotificationChanged::class]);
        DB::beginTransaction();
        $n = $this->notification($u, $o);
        Event::assertNotDispatched(StaffNotificationChanged::class);
        DB::commit();
        Event::assertDispatched(StaffNotificationChanged::class, fn ($e) => $e->action === 'created' && $e->userId === $u->id && $e->organizationId === $o->id && $e->notification['id'] === $n->id && $e->broadcastOn()[0]->name === 'private-staff.org.'.$o->id.'.user.'.$u->id);
        $this->actingAs($u)->patchJson('/api/notifications/'.$n->id.'/read')->assertOk();
        $this->patchJson('/api/notifications/'.$n->id.'/read')->assertOk();
        Event::assertDispatchedTimes(StaffNotificationChanged::class, 2);
        Event::assertDispatched(StaffNotificationChanged::class, fn ($e) => $e->action === 'read' && $e->notification['readAt'] !== null);
    }

    public function test_rollback_discards_notification_and_broadcast(): void
    {
        [$u,$o] = $this->fixture();
        Event::fake([StaffNotificationChanged::class]);
        DB::beginTransaction();
        $this->notification($u, $o);
        DB::rollBack();
        Event::assertNotDispatched(StaffNotificationChanged::class);
        $this->assertDatabaseCount('staff_notifications', 0);
    }

    public function test_broadcast_transport_failure_does_not_rollback_the_saved_notification(): void
    {
        [$u,$o] = $this->fixture();
        Event::listen(StaffNotificationChanged::class, fn () => throw new \RuntimeException('Reverb unavailable'));
        $n = DB::transaction(fn () => $this->notification($u, $o));
        $this->assertDatabaseHas('staff_notifications', ['id' => $n->id]);
    }
}
