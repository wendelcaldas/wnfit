<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'phone' => '(71) 99999-1234',
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
}
