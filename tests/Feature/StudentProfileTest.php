<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_and_health_can_be_completed_after_registration(): void
    {
        [$user, $organization] = $this->organizationWithUser();
        $student = Aluno::query()->create([
            'organizacao_id' => $organization->id,
            'nome' => 'Aluno Rapido',
            'telefone' => '(71) 99999-0000',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
        ]);

        $response = $this->actingAs($user)->patchJson("/api/students/{$student->id}", [
            'email' => 'aluno@example.com',
            'data_nascimento' => '1995-05-20',
            'contato_emergencia' => 'Maria',
            'telefone_emergencia' => '(71) 98888-0000',
            'objetivo' => 'Ganho de massa',
            'peso' => 78.5,
            'altura' => 180,
            'restricoes_medicas' => 'Nenhuma restricao conhecida',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('student.profile.emergencyContact', 'Maria')
            ->assertJsonPath('student.health.goal', 'Ganho de massa');

        $this->assertGreaterThan(50, $response->json('student.profileCompletion'));
        $this->assertDatabaseHas('alunos', [
            'id' => $student->id,
            'contato_emergencia' => 'Maria',
            'restricoes_medicas' => 'Nenhuma restricao conhecida',
        ]);
    }

    public function test_student_from_another_organization_cannot_be_updated(): void
    {
        [$user] = $this->organizationWithUser('academia-a');
        [, $otherOrganization] = $this->organizationWithUser('academia-b');
        $student = Aluno::query()->create([
            'organizacao_id' => $otherOrganization->id,
            'nome' => 'Aluno Externo',
            'telefone' => '(71) 90000-0000',
            'plano' => 'Plano Mensal',
            'status' => 'ativo',
        ]);

        $this->actingAs($user)->patchJson("/api/students/{$student->id}", ['nome' => 'Nome Alterado'])
            ->assertNotFound();
    }

    public function test_student_photo_is_saved_replaced_and_protected_by_organization(): void
    {
        Storage::fake('local');
        [$user, $organization] = $this->organizationWithUser();
        [$otherUser] = $this->organizationWithUser('outra');
        $student = Aluno::query()->create(['organizacao_id' => $organization->id, 'nome' => 'Aluno', 'telefone' => '71999990000', 'plano' => 'Mensal', 'status' => 'ativo', 'data_nascimento' => '2000-10-07']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-06 12:00:00'));
        $this->actingAs($user)->getJson('/api/students/'.$student->id)->assertOk()->assertJsonPath('student.age', 25)->assertJsonPath('student.photoUrl', null);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aSUQAAAAASUVORK5CYII=');
        $this->postJson('/api/students/'.$student->id.'/photo', ['photo' => UploadedFile::fake()->createWithContent('foto.png', $image)])->assertOk()->assertJsonStructure(['photoUrl']);
        $first = $student->fresh()->foto_path;
        Storage::disk('local')->assertExists($first);
        $this->get('/api/students/'.$student->id.'/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->postJson('/api/students/'.$student->id.'/photo', ['photo' => UploadedFile::fake()->createWithContent('nova.png', $image)])->assertOk();
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($student->fresh()->foto_path);
        $this->postJson('/api/students/'.$student->id.'/photo', ['photo' => UploadedFile::fake()->create('texto.txt', 1, 'text/plain')])->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->actingAs($otherUser)->get('/api/students/'.$student->id.'/photo')->assertNotFound();
        $this->postJson('/api/students/'.$student->id.'/photo', ['photo' => UploadedFile::fake()->createWithContent('foto.png', $image)])->assertNotFound();
    }

    private function organizationWithUser(string $slug = 'academia-teste'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::query()->create([
            'nome_fantasia' => $slug,
            'slug' => $slug,
            'tipo' => 'academia',
            'ativa' => true,
        ]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);

        return [$user, $organization];
    }
}
