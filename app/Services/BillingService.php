<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Assinatura;
use App\Models\Cobranca;
use App\Models\Plano;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public function createSubscriptionForStudent(
        Aluno $student,
        Plano $plan,
        Carbon|string $startDate,
        Carbon|string $dueDate,
        bool $autoRenew = true,
        string $paymentMethod = 'PIX',
    ): Assinatura {
        return DB::transaction(function () use ($student, $plan, $startDate, $dueDate, $autoRenew, $paymentMethod) {
            $subscription = Assinatura::query()->create([
                'organizacao_id' => $student->organizacao_id,
                'aluno_id' => $student->id,
                'plano_id' => $plan->id,
                'status' => 'ativa',
                'inicio_em' => Carbon::parse($startDate)->toDateString(),
                'proximo_vencimento' => Carbon::parse($dueDate)->toDateString(),
                'auto_renovacao' => $autoRenew,
                'metodo_pagamento' => $paymentMethod,
            ]);

            $this->generateCharge($subscription, Carbon::parse($dueDate));

            return $subscription;
        });
    }

    public function generateCharge(Assinatura $subscription, Carbon|string|null $dueDate = null): Cobranca
    {
        $due = $dueDate ? Carbon::parse($dueDate) : $subscription->proximo_vencimento;
        $competence = $due->format('m/Y');

        $charge = Cobranca::query()->firstOrNew([
            'assinatura_id' => $subscription->id,
            'competencia' => $competence,
        ]);

        if ($charge->exists && $charge->status === 'pago') {
            return $charge;
        }

        $charge->fill([
                'organizacao_id' => $subscription->organizacao_id,
                'aluno_id' => $subscription->aluno_id,
                'vencimento' => $due->toDateString(),
                'valor' => $subscription->plano->valor_mensal,
                'status' => $due->isPast() && ! $due->isToday() ? 'atrasado' : 'pendente',
                'forma_pagamento' => $subscription->metodo_pagamento,
                'link_pagamento' => 'https://pay.wnfit.test/'.Str::random(12),
        ])->save();

        $charge->eventos()->firstOrCreate(
            ['tipo' => 'cobranca_gerada'],
            [
                'descricao' => "Cobranca de {$competence} gerada automaticamente.",
                'ocorrido_em' => now(),
            ],
        );

        return $charge;
    }

    public function refreshOverdueCharges(int $organizationId): void
    {
        Cobranca::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', 'pendente')
            ->whereDate('vencimento', '<', today())
            ->get()
            ->each(function (Cobranca $charge) {
                $charge->update(['status' => 'atrasado']);
                $charge->eventos()->firstOrCreate(
                    ['tipo' => 'cobranca_atrasada'],
                    [
                        'descricao' => 'Sistema identificou inadimplencia e marcou a cobranca como atrasada.',
                        'ocorrido_em' => now(),
                    ],
                );
            });
    }

    public function sendCharge(Cobranca $charge): Cobranca
    {
        $charge->update(['enviado_em' => now()]);
        $charge->eventos()->create([
            'tipo' => 'link_enviado',
            'descricao' => 'Olá, sua mensalidade venceu. Segue link para pagamento.',
            'ocorrido_em' => now(),
        ]);

        return $charge;
    }

    public function registerPayment(Cobranca $charge, ?float $amount = null, string $method = 'PIX'): Cobranca
    {
        return DB::transaction(function () use ($charge, $amount, $method) {
            $paidAt = now();

            $charge->pagamento()->create([
                'valor' => $amount ?? $charge->valor,
                'metodo' => $method,
                'pago_em' => $paidAt,
            ]);

            $charge->update([
                'status' => 'pago',
                'pago_em' => $paidAt,
                'forma_pagamento' => $method,
            ]);

            $charge->eventos()->create([
                'tipo' => 'pagamento_confirmado',
                'descricao' => 'Pagamento confirmado.',
                'ocorrido_em' => $paidAt,
            ]);

            $subscription = $charge->assinatura;
            if ($subscription?->auto_renovacao) {
                $nextDueDate = $charge->vencimento->copy()->addMonthNoOverflow();
                $subscription->update(['proximo_vencimento' => $nextDueDate]);
                $this->generateCharge($subscription, $nextDueDate);
            }

            return $charge->refresh();
        });
    }
}
