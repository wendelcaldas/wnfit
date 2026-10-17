<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\StudentPhysicalAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPhysicalAssessmentTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $slug = 'assessment'): array
    {
        $user = User::factory()->create();
        $organization = Organizacao::create(['nome_fantasia' => $slug, 'slug' => $slug, 'tipo' => 'academia', 'ativa' => true]);
        $organization->usuarios()->attach($user->id, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno', 'telefone' => '71999990000', 'status' => 'ativo', 'plano' => 'Mensal', 'peso' => 80, 'altura' => 170]);
        return [$user, $student];
    }

    public function test_partial_assessments_are_ordered_compared_and_correctable_without_overwriting_legacy_values(): void
    {
        [$user, $student] = $this->context();
        $this->actingAs($user);
        $url = '/api/students/'.$student->id.'/physical-assessments';
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'assessments');
        $first = $this->postJson($url, ['assessed_on' => '2026-01-20', 'waist' => 80, 'weight' => 78.25, 'body_fat' => 0, 'method' => 'Fita'])->assertCreated()->assertJsonPath('assessment.measures.body_fat', 0)->json('assessment.id');
        $second = $this->postJson($url, ['assessed_on' => '2026-02-20', 'waist' => 78, 'arm_left' => 29.5])->assertCreated()->json('assessment.id');
        $this->postJson($url, ['assessed_on' => '2026-01-10', 'height' => 171.5])->assertCreated();
        $this->getJson($url)->assertJsonPath('assessments.0.id', $second)->assertJsonPath('assessments.1.id', $first);
        $this->getJson($url.'/'.$second)->assertOk()->assertJsonPath('previous.id', $first)->assertJsonPath('previous.measures.waist', 80)->assertJsonPath('previous.measures.arm_left', null);
        $this->getJson('/api/students/'.$student->id)->assertOk()->assertJsonPath('student.health.weight', '78.25')->assertJsonPath('student.health.height', '171.50');
        [$editor] = $this->context('editor');
        $student->organizacao->usuarios()->attach($editor->id, ['papel' => 'professor', 'status' => 'ativo']);
        // A dedicated user whose only organization is the student's organization.
        $editor->organizacoes()->where('organizacoes.id', '!=', $student->organizacao_id)->get()->each(fn ($org) => $editor->organizacoes()->detach($org->id));
        $this->actingAs($editor)->putJson($url.'/'.$second, ['assessed_on' => '2026-02-20', 'waist' => 77.5])->assertOk()->assertJsonPath('assessment.measures.arm_left', null);
        $this->assertDatabaseHas('student_physical_assessments', ['id' => $second, 'created_by' => $user->id, 'updated_by' => $editor->id]);
        $this->assertDatabaseHas('alunos', ['id' => $student->id, 'peso' => 80, 'altura' => 170]);
        $this->assertDatabaseCount('student_physical_assessments', 3);
    }

    public function test_optional_names_are_saved_edited_cleared_and_validated(): void
    {
        [$user, $student] = $this->context();
        $this->actingAs($user);
        $url = '/api/students/'.$student->id.'/physical-assessments';
        $id = $this->postJson($url, ['assessed_on' => '2026-01-20', 'weight' => 75, 'name' => 'Inicial'])->assertCreated()->assertJsonPath('assessment.name', 'Inicial')->json('assessment.id');
        $this->getJson($url)->assertOk()->assertJsonPath('assessments.0.name', 'Inicial');
        $this->putJson($url.'/'.$id, ['assessed_on' => '2026-01-20', 'weight' => 75, 'name' => 'Retorno'])->assertOk()->assertJsonPath('assessment.name', 'Retorno');
        $this->putJson($url.'/'.$id, ['assessed_on' => '2026-01-20', 'weight' => 75, 'name' => '   '])->assertOk()->assertJsonPath('assessment.name', null);
        $this->postJson($url, ['assessed_on' => '2026-01-20', 'weight' => 75, 'name' => str_repeat('a', 121)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseHas('student_physical_assessments', ['id' => $id, 'name' => null]);
    }

    public function test_dates_ranges_precision_and_empty_measurements_are_validated(): void
    {
        [$user, $student] = $this->context();
        $this->actingAs($user);
        $url = '/api/students/'.$student->id.'/physical-assessments';
        $this->postJson($url, ['assessed_on' => '2026-01-20'])->assertUnprocessable()->assertJsonValidationErrors('measures');
        $this->postJson($url, ['assessed_on' => '2099-01-20', 'weight' => 0, 'body_fat' => 101, 'height' => 301, 'waist' => 10.123])->assertUnprocessable()->assertJsonValidationErrors(['assessed_on', 'weight', 'body_fat', 'height', 'waist']);
        $this->postJson($url, ['assessed_on' => '2026-01-20', 'arm_left' => -2, 'notes' => str_repeat('x', 5001)])->assertUnprocessable()->assertJsonValidationErrors(['arm_left', 'notes']);
        $this->assertDatabaseCount('student_physical_assessments', 0);
    }

    public function test_assessments_are_isolated_by_student_and_organization(): void
    {
        [$user, $student] = $this->context();
        [$other, $otherStudent] = $this->context('other');
        $url = '/api/students/'.$student->id.'/physical-assessments';
        $id = $this->actingAs($user)->postJson($url, ['assessed_on' => '2026-01-20', 'weight' => 75])->assertCreated()->json('assessment.id');
        $this->actingAs($other)->getJson($url)->assertNotFound();
        $this->getJson($url.'/'.$id)->assertNotFound();
        $this->postJson($url, ['assessed_on' => '2026-01-20', 'weight' => 75])->assertNotFound();
        $this->putJson($url.'/'.$id, ['assessed_on' => '2026-01-20', 'weight' => 75])->assertNotFound();
        $this->getJson('/api/students/'.$otherStudent->id.'/physical-assessments/'.$id)->assertNotFound();
        $this->putJson('/api/students/'.$otherStudent->id.'/physical-assessments/'.$id, ['assessed_on' => '2026-01-20', 'weight' => 75])->assertNotFound();
    }
}
