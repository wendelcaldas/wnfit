<?php

namespace App\Services;

use App\Models\Assinatura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringBillingService
{
    public function __construct(private readonly BillingService $billing) {}

    public function missing(Assinatura $subscription, ?Carbon $from = null): array
    {
        if ($subscription->status !== 'ativa' || ! $subscription->auto_renovacao || $subscription->aluno?->status === 'pausado') {
            return [];
        }
        $months = match ($subscription->plano?->ciclo) {
            'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12, default => null,
        };
        if (! $months) {
            return [];
        }
        $anchor = $from ?? $subscription->recorrencia_inicio;
        if (! $anchor) {
            return [];
        }
        $end = today()->endOfMonth();
        if (today()->addDays(7)->gt($end)) {
            $end = today()->addDays(7)->endOfDay();
        }
        $existing = $subscription->cobrancas()->pluck('competencia')->all();
        $missing = [];
        for ($offset = 0; ; $offset += $months) {
            $due = $anchor->copy()->addMonthsNoOverflow($offset);
            if ($due->gt($end)) {
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

    public function sync(int $organizationId): int
    {
        $count = 0;
        Assinatura::query()->where('organizacao_id', $organizationId)->whereNotNull('recorrencia_inicio')
            ->where('status', 'ativa')->pluck('id')->each(function ($id) use (&$count) {
                DB::transaction(function () use ($id, &$count) {
                    $subscription = Assinatura::query()->lockForUpdate()->findOrFail($id);
                    foreach ($this->missing($subscription) as $item) {
                        $this->billing->generateCharge($subscription, $item['dueDate']);
                        $count++;
                    }
                });
            });

        return $count;
    }
}
