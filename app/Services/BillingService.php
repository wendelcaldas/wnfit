<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Assinatura;
use App\Models\Cobranca;
use App\Models\Plano;
use App\Services\Messaging\MessagingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(private readonly MessagingService $messaging) {}

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
                'recorrencia_inicio' => Carbon::parse($dueDate)->toDateString(),
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

        if ($charge->exists) {
            return $charge;
        }

        $charge->fill([
            'organizacao_id' => $subscription->organizacao_id,
            'aluno_id' => $subscription->aluno_id,
            'vencimento' => $due->toDateString(),
            'valor' => $subscription->plano->valor_mensal,
            'status' => $due->isPast() && ! $due->isToday() ? 'atrasado' : 'pendente',
            'forma_pagamento' => $subscription->metodo_pagamento,
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
        app(RecurringBillingService::class)->sync($organizationId);
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

    public function sendCharge(Cobranca $charge, bool $manual = false): Cobranca
    {
        abort_unless(in_array($charge->status, ['pendente', 'atrasado'], true), 422, 'Esta cobranca nao esta em aberto.');
        $message = $manual
            ? $this->messaging->prepareManualChargeReminder($charge, 'Mensagem preparada pelo gestor para envio manual.')
            : $this->messaging->sendChargeReminder($charge);

        if ($message->status === 'falhou') {
            $message = $this->messaging->prepareManualChargeReminder(
                $charge,
                $message->erro ?: 'Envio automatico indisponivel.',
            );
        }

        if ($message->enviado_em) {
            $charge->update(['enviado_em' => $message->enviado_em]);
        }

        $charge->eventos()->create([
            'tipo' => $message->status === 'manual_preparado' ? 'mensagem_manual_preparada' : 'link_enviado',
            'descricao' => $message->status === 'manual_preparado'
                ? 'Mensagem de cobranca preparada para envio manual pelo WhatsApp.'
                : 'Mensagem de cobranca enviada pelo WhatsApp.',
            'ocorrido_em' => now(),
        ]);

        return $charge->refresh();
    }

    public function registerPayment(Cobranca $charge, ?float $amount = null, string $method = 'PIX', Carbon|string|null $paymentDate = null): Cobranca
    {
        return DB::transaction(function () use ($charge, $amount, $method, $paymentDate) {
            $charge = Cobranca::query()->lockForUpdate()->findOrFail($charge->id);
            if ($charge->status === 'pago') {
                return $charge;
            }
            abort_unless(in_array($charge->status, ['pendente', 'atrasado'], true), 422, 'Esta cobranca nao pode receber pagamento.');
            $paidAt = $paymentDate ? Carbon::parse($paymentDate) : now();

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
            if ($subscription) {
                $next = $subscription->cobrancas()->whereIn('status', ['pendente', 'atrasado'])->orderBy('vencimento')->first()?->vencimento;
                if (! $next && $subscription->auto_renovacao && $subscription->status === 'ativa') {
                    $months = match ($subscription->plano->ciclo) {
                        'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12, default => null,
                    };
                    if ($months) {
                        $anchor = $subscription->recorrencia_inicio ?? $charge->vencimento;
                        $offset = 0;
                        do {
                            $offset += $months;
                            $next = $anchor->copy()->addMonthsNoOverflow($offset);
                        } while ($next->lte($charge->vencimento));
                    }
                }
                if ($next) {
                    $subscription->update(['proximo_vencimento' => $next]);
                }
            }

            return $charge->refresh();
        });
    }
}
