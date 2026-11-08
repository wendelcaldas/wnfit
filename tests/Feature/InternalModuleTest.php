<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_explicit_internal_account_can_manage_modules_across_organizations(): void
    {
        config(['services.messaging.module_organization_slugs' => []]);
        $owner = User::factory()->create(['email' => 'wendelsouza.dev@gmail.com']);
        $internal = User::factory()->create();
        $internal->forceFill(['is_wnfit_internal' => true])->save();
        $organization = Organizacao::create(['nome_fantasia' => 'Outro ambiente', 'slug' => 'outro-ambiente', 'tipo' => 'studio', 'ativa' => true]);
        $organization->usuarios()->attach($owner->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        $url = '/api/internal/modules';
        $payload = ['module' => 'messaging', 'enabled' => true];

        $this->actingAs($owner)->getJson('/api/me')->assertOk()
            ->assertJsonPath('user.isWnfitInternal', false)
            ->assertJsonPath('organization.modules.messaging', false);
        $this->getJson($url)->assertForbidden();
        $this->patchJson($url.'/'.$organization->id, $payload)->assertForbidden();

        $this->actingAs($internal)->getJson($url.'?q=outro-ambiente')->assertOk()
            ->assertJsonPath('data.0.slug', 'outro-ambiente');
        $this->patchJson($url.'/'.$organization->id, $payload)->assertOk()
            ->assertJsonPath('modules.messaging', true);
        $this->patchJson($url.'/'.$organization->id, ['module' => 'agenda', 'enabled' => false])
            ->assertUnprocessable();

        $this->actingAs($owner)->getJson('/api/organization/messaging')->assertOk();
        $this->actingAs($internal);
        foreach (['students', 'workouts', 'finance', 'schedule', 'messaging'] as $module) {
            $this->patchJson($url.'/'.$organization->id, ['module' => $module, 'enabled' => false])->assertOk();
        }
        $this->actingAs($owner);
        foreach (['/api/students', '/api/workouts', '/api/finance', '/api/schedule', '/api/organization/messaging'] as $endpoint) {
            $this->getJson($endpoint)->assertForbidden();
        }
        $this->getJson('/api/public/schedule/outro-ambiente')->assertNotFound();
    }
}
