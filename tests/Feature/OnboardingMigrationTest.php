<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnboardingMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Run schema changes outside a transaction, as the production migrator does.
        $this->artisan('migrate:fresh')->assertExitCode(0);
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_migration_preserves_existing_organizations_and_login(): void
    {
        $migration = require database_path('migrations/2026_10_25_000001_add_onboarding_profile_to_organizacoes.php');
        $migration->down();
        Schema::table('organizacoes', function (Blueprint $table) {
            $table->string('tipo', 30)->nullable(false)->change();
        });

        $user = User::factory()->create(['email' => 'legado@example.com']);
        foreach (['personal', 'studio', 'academia'] as $type) {
            $organization = Organizacao::create([
                'nome_fantasia' => 'Negócio '.$type, 'slug' => 'legado-'.$type,
                'tipo' => $type, 'telefone_contato' => '(71) 99999-1234',
                'email_contato' => 'legado@example.com', 'ativa' => true,
            ]);
            $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        }
        $organizations = DB::table('organizacoes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $memberships = DB::table('organizacao_usuario')->get()->all();
        $users = DB::table('users')->get()->all();

        $this->assertFalse($migration->withinTransaction);
        $migration->up();
        $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        $after = DB::table('organizacoes')->orderBy('id')->get()->map(function ($row) {
            $this->assertNull($row->onboarding_profile);
            $data = (array) $row;
            unset($data['onboarding_profile']);

            return $data;
        })->all();
        $this->assertSame($organizations, $after);
        $this->assertEquals($memberships, DB::table('organizacao_usuario')->get()->all());
        $this->assertEquals($users, DB::table('users')->get()->all());
        $this->postJson('/api/login', ['email' => 'legado@example.com', 'password' => 'password'])
            ->assertOk()->assertJsonPath('organization.type', 'personal')
            ->assertJsonPath('organization.phone', '(71) 99999-1234')
            ->assertJsonPath('organization.onboarding_profile', null);

        $new = Organizacao::create(['nome_fantasia' => 'Novo Ambiente', 'slug' => 'novo-ambiente']);
        $this->assertNull($new->fresh()->tipo);
    }
}
