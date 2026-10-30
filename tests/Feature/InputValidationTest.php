<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\User;
use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class InputValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_rule_accepts_masks_and_rejects_letters_and_invalid_lengths(): void
    {
        foreach (['71996651964', '(71) 99665-1964', '+55 (71) 99665-1964', '5571996651964', '(71) 3333-4444'] as $phone) {
            $this->assertTrue(Validator::make(['phone' => $phone], ['phone' => new BrazilianPhone])->passes(), $phone);
        }
        foreach (['abc71996651964', '71996651964abc', '123', '719966519641234', '(00) 99665-1964', '7198765432', '---'] as $phone) {
            $this->assertFalse(Validator::make(['phone' => $phone], ['phone' => new BrazilianPhone])->passes(), $phone);
        }
    }

    public function test_profile_rejects_invalid_structured_data_without_partial_updates(): void
    {
        $owner = User::factory()->create();
        $organization = Organizacao::create(['nome_fantasia' => 'Inputs', 'slug' => 'inputs', 'tipo' => 'studio', 'ativa' => true]);
        $organization->usuarios()->attach($owner, ['papel' => 'proprietario', 'status' => 'ativo']);
        $student = Aluno::create(['organizacao_id' => $organization->id, 'nome' => 'Aluno', 'telefone' => '71996651964', 'status' => 'ativo']);
        $this->actingAs($owner)->patchJson('/api/students/'.$student->id, [
            'nome' => 'Não deve salvar', 'telefone_emergencia' => 'abc71996651964',
            'cpf' => '123', 'cep' => 'ABCDE', 'estado' => 'ZZ', 'peso' => -1,
            'altura' => 9999, 'data_nascimento' => today()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['telefone_emergencia', 'cpf', 'cep', 'estado', 'peso', 'altura', 'data_nascimento']);
        $this->assertSame('Aluno', $student->fresh()->nome);
        $this->patchJson('/api/students/'.$student->id, [
            'telefone_emergencia' => '(71) 3333-4444', 'cpf' => '123.456.789-01', 'cep' => '40100-120', 'estado' => 'BA',
        ])->assertOk();
    }
}
