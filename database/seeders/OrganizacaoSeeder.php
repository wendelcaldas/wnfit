<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Aula;
use App\Models\Checkin;
use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\Receita;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Support\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizacaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var BillingService $billing */
        $billing = app(BillingService::class);

        $usuarioAdministrador = User::query()->firstOrCreate(
            ['email' => 'admin@wnfit.test'],
            [
                'name' => 'Administrador WNFit',
                'password' => Hash::make('password'),
            ],
        );

        $organizacao = Organizacao::query()->firstOrCreate(
            ['slug' => 'wnfit-demo'],
            [
                'nome_fantasia' => 'WNFit Demo',
                'razao_social' => 'WNFit Tecnologia e Performance Ltda',
                'tipo' => 'studio',
                'email_contato' => 'contato@wnfit.test',
                'telefone_contato' => '(71) 99999-0000',
            ],
        );

        $organizacao->usuarios()->syncWithoutDetaching([
            $usuarioAdministrador->id => [
                'papel' => 'proprietario',
                'status' => 'ativo',
            ],
        ]);

        $plans = collect([
            ['nome' => 'Plano Mensal', 'valor_mensal' => 120, 'ciclo' => 'mensal'],
            ['nome' => 'Plano Trimestral', 'valor_mensal' => 320, 'ciclo' => 'trimestral'],
            ['nome' => 'Plano Anual', 'valor_mensal' => 980, 'ciclo' => 'anual'],
        ])->mapWithKeys(fn (array $plan) => [
            $plan['nome'] => Plano::query()->updateOrCreate(
                ['organizacao_id' => $organizacao->id, 'nome' => $plan['nome']],
                $plan + ['organizacao_id' => $organizacao->id, 'ativo' => true],
            ),
        ]);

        $alunos = collect([
            ['nome' => 'Julia Mendes', 'email' => 'julia.mendes@email.com', 'telefone' => '(11) 99999-1234', 'objetivo' => 'Emagrecimento', 'treinador' => 'Lucas Oliveira', 'plano' => 'Plano Mensal', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
            ['nome' => 'Pedro Henrique', 'email' => 'pedro@email.com', 'telefone' => '(11) 98888-5678', 'objetivo' => 'Ganho de massa', 'treinador' => 'Camila Alves', 'plano' => 'Plano Trimestral', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
            ['nome' => 'Mariana Costa', 'email' => 'mari@email.com', 'telefone' => '(11) 97777-9012', 'objetivo' => 'Saude', 'treinador' => 'Lucas Oliveira', 'plano' => 'Plano Mensal', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
            ['nome' => 'Rafael Souza', 'email' => 'rafael@email.com', 'telefone' => '(11) 96666-3456', 'objetivo' => 'Performance', 'treinador' => 'Camila Alves', 'plano' => 'Plano Anual', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
            ['nome' => 'Amanda Lima', 'email' => 'amanda@email.com', 'telefone' => '(11) 95555-7890', 'objetivo' => 'Condicionamento', 'treinador' => 'Lucas Oliveira', 'plano' => 'Plano Mensal', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
            ['nome' => 'Felipe Santos', 'email' => 'felipe@email.com', 'telefone' => '(11) 94444-1122', 'objetivo' => 'Definicao', 'treinador' => 'Camila Alves', 'plano' => 'Plano Trimestral', 'status' => 'ativo', 'cidade' => 'Sao Paulo', 'estado' => 'SP'],
        ])->map(function (array $data, int $index) use ($organizacao) {
            return Aluno::query()->updateOrCreate(
                ['organizacao_id' => $organizacao->id, 'email' => $data['email']],
                $data + [
                    'organizacao_id' => $organizacao->id,
                    'data_nascimento' => now()->subYears(25 + $index)->toDateString(),
                    'genero' => $index % 2 === 0 ? 'Feminino' : 'Masculino',
                    'cpf' => '123.456.789-1'.$index,
                    'rg' => '12.345.678-'.$index,
                    'profissao' => $index % 2 === 0 ? 'Designer' : 'Analista',
                    'endereco' => 'Rua das Flores',
                    'numero' => (string) (120 + $index),
                    'bairro' => 'Vila Madalena',
                    'cep' => '05432-120',
                    'peso' => 62 + $index,
                    'altura' => 165 + $index,
                    'unidade' => 'Unidade Vila Madalena',
                    'como_conheceu' => 'Indicacao',
                    'observacoes' => 'Prefere treinos pela manha. Historico de boa frequencia.',
                    'vencimento' => now()->addDays($index === 4 ? -12 : 12 + $index * 3)->toDateString(),
                ],
            );
        });

        $alunos->each(function (Aluno $aluno, int $index) use ($plans, $billing) {
            $plan = $plans[$aluno->plano] ?? $plans['Plano Mensal'];
            $subscription = $aluno->assinatura()->first();

            if (! $subscription) {
                $subscription = $billing->createSubscriptionForStudent(
                    $aluno,
                    $plan,
                    now()->subMonths(5)->day(24),
                    Carbon::parse($aluno->vencimento),
                    true,
                    'PIX',
                );
            }

            foreach (range(5, 1) as $monthsAgo) {
                $dueDate = now()->subMonths($monthsAgo)->day(24);
                $charge = $billing->generateCharge($subscription->load('plano'), $dueDate);

                if ($charge->status !== 'pago') {
                    $billing->registerPayment($charge, (float) $plan->valor_mensal, 'PIX');
                }
            }

            $currentCharge = $billing->generateCharge($subscription->load('plano'), Carbon::parse($aluno->vencimento));

            if ($index === 4) {
                $currentCharge->update(['status' => 'atrasado']);
                $billing->sendCharge($currentCharge);
            }
        });

        $today = Carbon::today();

        foreach ([
            ['07:00', 'Funcional', 'Sala 1', 'Lucas', 15, 8],
            ['08:00', 'Pilates', 'Sala 2', 'Camila', 12, 6],
            ['18:00', 'HIIT', 'Sala 1', 'Lucas', 15, 10],
            ['19:00', 'Spinning', 'Sala 3', 'Pedro', 12, 7],
        ] as [$hora, $nome, $sala, $instrutor, $capacidade, $reservas]) {
            Aula::query()->updateOrCreate(
                ['organizacao_id' => $organizacao->id, 'data' => $today->toDateString(), 'hora' => $hora],
                compact('nome', 'sala', 'instrutor', 'capacidade', 'reservas') + ['organizacao_id' => $organizacao->id, 'data' => $today->toDateString()],
            );
        }

        $checkinsByDay = [42, 55, 80, 68, 92, 58, 76];
        foreach ($checkinsByDay as $offset => $total) {
            $date = $today->copy()->subDays(6 - $offset);
            Checkin::query()
                ->where('organizacao_id', $organizacao->id)
                ->whereDate('realizado_em', $date)
                ->delete();

            for ($i = 0; $i < $total; $i++) {
                Checkin::query()->create([
                    'organizacao_id' => $organizacao->id,
                    'aluno_id' => $alunos[$i % $alunos->count()]->id,
                    'realizado_em' => $date->copy()->setTime(6 + ($i % 14), $i % 60),
                ]);
            }
        }

        foreach ([8800, 10200, 11850, 13900, 15440, 18540] as $offset => $value) {
            $competencia = $today->copy()->subMonths(5 - $offset)->startOfMonth();

            Receita::query()->updateOrCreate(
                ['organizacao_id' => $organizacao->id, 'competencia' => $competencia->toDateString(), 'descricao' => 'Mensalidades'],
                ['valor' => $value],
            );
        }
    }
}
