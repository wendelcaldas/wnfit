<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_session_endpoint_returns_a_csrf_token(): void
    {
        $response = $this->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonStructure(['csrf', 'user', 'organization'])
            ->assertJsonPath('user', null)
            ->assertJsonPath('organization', null);
    }

    public function test_logout_returns_the_regenerated_csrf_token(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['_token' => 'token-before-logout'])
            ->postJson('/api/logout');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['csrf']);

        $this->assertNotSame('token-before-logout', $response->json('csrf'));
        $this->assertGuest();
    }

    public function test_new_client_registration_creates_an_isolated_ready_to_use_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '  Maria Gestora  ',
            'email' => '  MARIA@EXAMPLE.COM  ',
            'company' => '  Studio Movimento  ',
            'company_type' => 'studio',
            'phone' => '71999991234',
            'password' => 'Senha123!',
            'password_confirmation' => 'Senha123!',
            'terms' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('user.name', 'Maria Gestora')
            ->assertJsonPath('user.email', 'maria@example.com')
            ->assertJsonPath('organization.name', 'Studio Movimento')
            ->assertJsonPath('organization.type', 'studio');

        $user = User::query()->where('email', 'maria@example.com')->firstOrFail();
        $organization = Organizacao::query()->where('slug', 'studio-movimento')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('organizacao_usuario', [
            'organizacao_id' => $organization->id,
            'user_id' => $user->id,
            'papel' => 'proprietario',
            'status' => 'ativo',
        ]);
        $this->assertDatabaseHas('planos', [
            'organizacao_id' => $organization->id,
            'nome' => 'Plano Mensal',
            'valor_mensal' => 120,
            'ativo' => true,
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.0.value', '0')
            ->assertJsonPath('organization.name', 'Studio Movimento');

        $this->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(0, 'students');
    }

    public function test_registration_requires_terms_and_does_not_leave_partial_records(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Cliente Teste',
            'email' => 'cliente@example.com',
            'company' => 'Academia Teste',
            'company_type' => 'academia',
            'password' => 'Senha123!',
            'password_confirmation' => 'Senha123!',
            'terms' => false,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['terms']);

        $this->assertDatabaseMissing('users', ['email' => 'cliente@example.com']);
        $this->assertDatabaseMissing('organizacoes', ['slug' => 'academia-teste']);
    }

    public function test_registered_client_can_logout_and_login_again(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Joao Cliente',
            'email' => 'joao@example.com',
            'company' => 'Academia Centro',
            'company_type' => 'academia',
            'password' => 'Senha123!',
            'password_confirmation' => 'Senha123!',
            'terms' => true,
        ])->assertCreated();

        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();

        $this->postJson('/api/login', [
            'email' => 'JOAO@EXAMPLE.COM',
            'password' => 'Senha123!',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'joao@example.com')
            ->assertJsonPath('organization.name', 'Academia Centro');

        $this->assertAuthenticated();
    }

    private function profileRegistration(array $profile): array
    {
        return [
            'name' => 'Cliente Perfil', 'email' => 'perfil@example.com',
            'company' => 'Meu Ambiente', 'password' => 'Senha123!',
            'password_confirmation' => 'Senha123!', 'terms' => true,
            'onboarding_profile' => $profile,
        ];
    }

    public function test_profile_registration_persists_answers_without_inventing_a_business_type(): void
    {
        $profile = ['student_range' => '100_plus', 'service_mode' => 'online', 'team' => 'solo', 'priorities' => ['workouts', 'progress']];
        $response = $this->postJson('/api/register', $this->profileRegistration($profile));
        $response->assertCreated()->assertJsonPath('organization.type', null)->assertJsonPath('organization.onboarding_profile', $profile);
        $organization = Organizacao::where('slug', 'meu-ambiente')->firstOrFail();
        $this->assertNull($organization->tipo);
        $this->assertSame($profile, $organization->onboarding_profile);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('organization.onboarding_profile', $profile);
        $this->postJson('/api/logout')->assertOk();
        $this->postJson('/api/login', ['email' => 'perfil@example.com', 'password' => 'Senha123!'])
            ->assertOk()->assertJsonPath('organization.onboarding_profile', $profile);
    }

    public function test_invalid_profiles_leave_no_partial_account(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $valid = ['student_range' => 'starting', 'service_mode' => 'hybrid', 'team' => 'team', 'priorities' => ['schedule']];
        $invalid = [
            [[], 'onboarding_profile'],
            [['team' => 'solo'], 'onboarding_profile.student_range'],
            [array_replace($valid, ['service_mode' => 'invalid']), 'onboarding_profile.service_mode'],
            [array_replace($valid, ['team' => 'invalid']), 'onboarding_profile.team'],
            [array_replace($valid, ['student_range' => 'invalid']), 'onboarding_profile.student_range'],
            [array_replace($valid, ['priorities' => []]), 'onboarding_profile.priorities'],
            [array_replace($valid, ['priorities' => ['workouts', 'workouts']]), 'onboarding_profile.priorities.0'],
            [array_replace($valid, ['priorities' => ['workouts', 'schedule', 'finance', 'events']]), 'onboarding_profile.priorities'],
            [array_replace($valid, ['priorities' => ['invalid']]), 'onboarding_profile.priorities.0'],
            [array_replace($valid, ['plan' => 'premium']), 'onboarding_profile'],
        ];
        foreach ($invalid as [$profile, $field]) {
            $this->postJson('/api/register', $this->profileRegistration($profile))->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertDatabaseMissing('users', ['email' => 'perfil@example.com']);
            $this->assertDatabaseMissing('organizacoes', ['slug' => 'meu-ambiente']);
        }
    }

    public function test_organization_profile_is_not_exposed_to_another_account(): void
    {
        $profile = ['student_range' => '1_20', 'service_mode' => 'in_person', 'team' => 'team', 'priorities' => ['finance']];
        $this->postJson('/api/register', $this->profileRegistration($profile))->assertCreated();
        $this->postJson('/api/logout')->assertOk();
        $other = $this->profileRegistration($profile);
        unset($other['onboarding_profile']);
        $other['email'] = 'outro@example.com';
        $other['company'] = 'Outro Ambiente';
        $other['company_type'] = 'studio';
        $this->postJson('/api/register', $other)->assertCreated()->assertJsonPath('organization.onboarding_profile', null);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('organization.name', 'Outro Ambiente')->assertJsonPath('organization.onboarding_profile', null);
    }

    public function test_email_availability_normalizes_email_and_does_not_create_accounts(): void
    {
        User::factory()->create(['email' => 'existente@example.com']);
        $this->postJson('/api/register/check-email', ['email' => ' EXISTENTE@EXAMPLE.COM '])
            ->assertOk()->assertExactJson(['available' => false]);
        $this->postJson('/api/register/check-email', ['email' => 'novo@example.com'])
            ->assertOk()->assertExactJson(['available' => true]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizacoes', 0);
        $this->assertGuest();
    }

    public function test_email_availability_validates_format_and_limits_requests(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.51']);
        $this->postJson('/api/register/check-email', ['email' => 'invalido'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        for ($i = 0; $i < 19; $i++) {
            $this->postJson('/api/register/check-email', ['email' => 'novo@example.com'])->assertOk();
        }
        $this->postJson('/api/register/check-email', ['email' => 'novo@example.com'])->assertTooManyRequests();
    }

    public function test_final_registration_still_rejects_an_email_taken_after_availability_check(): void
    {
        $this->postJson('/api/register/check-email', ['email' => 'perfil@example.com'])->assertJsonPath('available', true);
        User::factory()->create(['email' => 'perfil@example.com']);
        $profile = ['student_range' => 'starting', 'service_mode' => 'online', 'team' => 'solo', 'priorities' => ['workouts']];
        $this->postJson('/api/register', $this->profileRegistration($profile))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('organizacoes', 0);
        $this->assertGuest();
    }

    public function test_registration_validates_phone_and_short_names_without_partial_records(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $profile = ['student_range' => 'starting', 'service_mode' => 'online', 'team' => 'solo', 'priorities' => ['workouts']];
        foreach ([['phone', '123'], ['phone', '7199999abcd'], ['phone', '(71) 99999-1234'], ['phone', '1234567890123456'], ['name', ' '], ['name', 'A'], ['company', 'X']] as [$field, $value]) {
            $payload = $this->profileRegistration($profile);
            $payload[$field] = $value;
            $this->postJson('/api/register', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->assertDatabaseCount('organizacoes', 0);
            $this->assertDatabaseCount('users', 0);
        }
        $payload = $this->profileRegistration($profile);
        $payload['phone'] = '5571999991234';
        $this->postJson('/api/register', $payload)->assertCreated()->assertJsonPath('organization.phone', '5571999991234');
    }
}
