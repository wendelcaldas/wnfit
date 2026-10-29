<?php

namespace Database\Seeders;

use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Organizacao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new \RuntimeException('Dados de demonstração disponíveis apenas no ambiente local.');
        }

        $org = Organizacao::where('slug', 'wnstudio')->firstOrFail();
        $month = Carbon::now('America/Bahia')->startOfMonth();
        $rows = [
            ['Ana Oliveira', 'mensalidade', 'Mensalidade · funcional', 180, 5, 5],
            ['Bruno Santos', 'mensalidade', 'Mensalidade · musculação', 220, 10, 9],
            ['Carla Mendes', 'mensalidade', 'Mensalidade · personal', 350, 15, 18],
            ['Diego Almeida', 'mensalidade', 'Mensalidade · funcional', 180, 8, null],
            ['Elisa Costa', 'mensalidade', 'Mensalidade · musculação', 220, 20, null],
            ['Felipe Lima', 'mensalidade', 'Mensalidade · personal', 350, 30, null],
            ['Gabriela Rocha', 'wellhub', 'Wellhub · 12 check-ins simulados', 156, 15, 16],
            ['Henrique Souza', 'totalpass', 'TotalPass · 10 check-ins simulados', 140, 20, 22],
            ['Isabela Dias', 'wellhub', 'Wellhub · 8 check-ins simulados', 104, 30, null],
            ['João Ribeiro', 'totalpass', 'TotalPass · 14 check-ins simulados', 196, 25, null],
        ];

        DB::transaction(function () use ($org, $month, $rows) {
            foreach ($rows as $index => [$name, $source, $description, $value, $dueDay, $paidDay]) {
                $student = Aluno::firstOrCreate([
                    'organizacao_id' => $org->id,
                    'email' => 'finance-demo-'.($index + 1).'@example.invalid',
                ], [
                    'nome' => $name.' (Demo)', 'status' => 'ativo', 'modalidade' => 'presencial',
                    'plano' => $description, 'observacoes' => 'Aluno fictício para demonstração financeira local. Sem envio de mensagens.',
                ]);
                $due = $month->copy()->day(min($dueDay, $month->daysInMonth));
                $paid = $paidDay ? $month->copy()->day($paidDay)->setTime(10, 30) : null;
                $charge = Cobranca::firstOrCreate([
                    'organizacao_id' => $org->id, 'aluno_id' => $student->id,
                    'competencia' => $month->format('m/Y'), 'descricao' => '[DEMO] '.$description,
                ], [
                    'origem' => $source, 'valor' => $value, 'vencimento' => $due,
                    'status' => $paid ? 'pago' : ($due->lt(Carbon::today('America/Bahia')) ? 'atrasado' : 'pendente'),
                    'forma_pagamento' => $source === 'mensalidade' ? 'PIX' : 'Transferência',
                    'pago_em' => $paid,
                ]);
                if ($charge->wasRecentlyCreated && $paid) {
                    $charge->pagamento()->create(['valor' => $value, 'metodo' => $charge->forma_pagamento, 'pago_em' => $paid]);
                }
            }

            // Recuperação: cobrança do mês anterior, recebida neste mês.
            $student = Aluno::where('organizacao_id', $org->id)->where('email', 'finance-demo-4@example.invalid')->firstOrFail();
            $previous = $month->copy()->subMonth();
            $paid = $month->copy()->day(24)->setTime(14, 15);
            $charge = Cobranca::firstOrCreate([
                'organizacao_id' => $org->id, 'aluno_id' => $student->id,
                'competencia' => $previous->format('m/Y'), 'descricao' => '[DEMO] Mensalidade recuperada do mês anterior',
            ], [
                'origem' => 'mensalidade', 'valor' => 180, 'vencimento' => $previous->copy()->day(8),
                'status' => 'pago', 'forma_pagamento' => 'PIX', 'pago_em' => $paid,
            ]);
            if ($charge->wasRecentlyCreated) {
                $charge->pagamento()->create(['valor' => 180, 'metodo' => 'PIX', 'pago_em' => $paid]);
            }
        });
        $this->command?->info('WNSTUDIO: 11 movimentações de demonstração em '.$month->format('m/Y').'. Execução sem duplicação.');
    }
}
