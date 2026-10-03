<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Exercicio;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\StudentAccount;
use App\Models\Treino;
use App\Models\User;
use App\Services\WorkoutPublishingService;
use Illuminate\Database\Seeder;

class StudentPortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || basename(config('database.connections.sqlite.database')) !== 'portal-validation.sqlite' || config('database.default') !== 'sqlite') {
            throw new \RuntimeException('Use somente o banco local portal-validation.sqlite para esta demonstração.');
        }
        $owner = User::firstOrCreate(['email' => 'professor@demo.wnfit.test'], ['name' => 'Professor Demo', 'password' => 'ProfessorDemo2026!']);
        $organization = Organizacao::firstOrCreate(['slug' => 'portal-demo'], ['nome_fantasia' => 'Studio Movimento', 'tipo' => 'studio', 'ativa' => true]);
        $organization->usuarios()->syncWithoutDetaching([$owner->id => ['papel' => 'proprietario', 'status' => 'ativo']]);
        Plano::firstOrCreate(['organizacao_id' => $organization->id, 'nome' => 'Plano Mensal'], ['valor_mensal' => 120, 'ativo' => true]);
        $workout = Treino::firstOrCreate(['organizacao_id' => $organization->id, 'nome' => 'Força e constância · ABC'], ['criado_por' => $owner->id, 'objetivo' => 'Hipertrofia', 'nivel' => 'iniciante', 'sessoes_semana' => 3, 'duracao_semanas' => 8, 'status' => 'ativo', 'descricao' => 'Respeite os intervalos e priorize a execução. Avise seu professor se sentir desconforto.']);
        if (! $workout->dias()->exists()) {
            foreach (['Treino A' => ['Peito e tríceps', 'Supino reto', 'Tríceps na polia', 'Flexão de braços'], 'Treino B' => ['Costas e bíceps', 'Puxada frontal', 'Remada baixa', 'Rosca direta'], 'Treino C' => ['Pernas e ombros', 'Agachamento livre', 'Leg press', 'Elevação lateral']] as $name => $items) {
                $day = $workout->dias()->create(['nome' => $name, 'foco' => $items[0], 'ordem' => $workout->dias()->count()]);
                foreach (array_slice($items, 1) as $index => $exerciseName) {
                    $exercise = Exercicio::firstOrCreate(['organizacao_id' => $organization->id, 'nome' => $exerciseName], ['grupo_muscular' => $items[0], 'equipamento' => 'Academia', 'categoria' => 'musculacao', 'nivel' => 'iniciante', 'instrucoes' => 'Realize o movimento de forma controlada, seguindo a orientação presencial do seu professor.', 'origem' => 'organizacao', 'ativo' => true]);
                    $day->exercicios()->create(['exercicio_id' => $exercise->id, 'nome' => $exerciseName, 'grupo_muscular' => $items[0], 'series' => 3, 'repeticoes' => '10–12', 'carga' => '20 kg', 'descanso_segundos' => 60, 'observacoes' => 'Priorize a técnica antes de aumentar a carga.', 'ordem' => $index]);
                }
            }
        }
        foreach (['wendel.demo' => ['Wendel Souza Caldas', false], 'primeiro.demo' => ['Marina Demo', true], 'semficha.demo' => ['Lucas Demo', false]] as $username => [$name, $firstAccess]) {
            $student = Aluno::firstOrCreate(['organizacao_id' => $organization->id, 'nome' => $name], ['telefone' => '(71) 99999-0000', 'status' => 'ativo', 'plano' => 'Plano Mensal', 'treinador' => $owner->name]);
            StudentAccount::firstOrCreate(['aluno_id' => $student->id], ['username' => $username, 'password' => 'TreinoDemo2026!', 'must_change_password' => $firstAccess]);
            if ($username !== 'semficha.demo' && ! app(WorkoutPublishingService::class)->current($student->id)) {
                app(WorkoutPublishingService::class)->publish($student, $workout, $owner->id);
            }
        }
    }
}
