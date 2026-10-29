<?php

namespace App\Services;

use App\Models\Assinatura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringBillingService
{
    public function __construct(private readonly BillingService $billing) {}

    public function missing(Assinatura $subscription, ?Carbon $from = null, ?Carbon $through = null): array
    {
        if ($subscription->status !== 'ativa' || $subscription->aluno?->status === 'pausado') {
            return [];
        }
        $months = match ($subscription->plano?->ciclo) {
            'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12, default => null,
        };
        if (! $months) {
            return [];
        }
        $earliest = $subscription->cobrancas()->orderBy('vencimento')->first()?->vencimento;
        if ($earliest?->lt($subscription->inicio_em)) {
            $earliest = null;
        }
        $anchor = $from ?? $subscription->recorrencia_inicio ?? $earliest ?? $subscription->proximo_vencimento;
        if (! $anchor) {
            return [];
        }
        $end = today()->endOfMonth();
        if (today()->addDays(7)->gt($end)) {
            $end = today()->addDays(7)->endOfDay();
        }
        if ($through && $through->gt($end)) {
            $end = $through->copy()->endOfDay();
        }
        $existing = $subscription->cobrancas()->pluck('competencia')->all();
        $missing = [];
        for ($offset = 0; ; $offset += $months) {
            $due = $anchor->copy()->addMonthsNoOverflow($offset);
            if ((! $subscription->auto_renovacao && $offset > 0) || $due->gt($end)) {
                break;
            }
            if (($subscription->encerramento_em && $due->gte($subscription->encerramento_em))
                || ($subscription->pausa_em && $due->gte($subscription->pausa_em))) {
                break;
            }
            if ($due->lt($subscription->inicio_em) || in_array($due->format('m/Y'), $existing, true)) {
                continue;
            }
            $missing[] = ['dueDate' => $due->toDateString(), 'competence' => $due->format('m/Y'), 'value' => (float) $subscription->plano->valor_mensal];
        }

        return $missing;
    }

    public function sync(int $organizationId, ?Carbon $through = null): int
    {
        $count = 0;
        Assinatura::query()->where('organizacao_id', $organizationId)
            ->where('status', 'ativa')->pluck('id')->each(function ($id) use (&$count, $through) {
                DB::transaction(function () use ($id, &$count, $through) {
                    $subscription = Assinatura::query()->lockForUpdate()->findOrFail($id);
                    if (! $subscription->recorrencia_inicio && $subscription->auto_renovacao && $subscription->aluno?->status !== 'pausado') {
                        $earliest = $subscription->cobrancas()->orderBy('vencimento')->first()?->vencimento;
                        $anchor = $earliest && $earliest->gte($subscription->inicio_em) ? $earliest : $subscription->proximo_vencimento;
                        if ($anchor) {
                            $subscription->update(['recorrencia_inicio' => $anchor]);
                        }
                    }
                    foreach ($this->missing($subscription, through: $through) as $item) {
                        $this->billing->generateCharge($subscription, $item['dueDate']);
                        $count++;
                    }
                });
            });

        return $count;
    }
}
