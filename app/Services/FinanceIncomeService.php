<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Receita;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FinanceIncomeService
{
    public function entries(int $organizationId, Carbon $start, Carbon $end): Collection
    {
        $charges = Cobranca::with(['aluno', 'assinatura.plano', 'pagamento'])->where('organizacao_id', $organizationId)
            ->where('status', 'pago')->where(function ($query) use ($start, $end) {
                $query->whereHas('pagamento', fn ($q) => $q->whereBetween('pago_em', [$start, $end]))
                    ->orWhere(fn ($q) => $q->whereDoesntHave('pagamento')->whereBetween('pago_em', [$start, $end]));
            })->get()->map(function ($charge) {
                $method = $charge->pagamento?->metodo ?? $charge->forma_pagamento;
                $source = in_array($charge->origem, ['totalpass', 'wellhub']) ? $charge->origem
                    : (mb_strtolower($method ?? '') === 'boleto' ? 'boleto' : 'direto');
                $date = $charge->pagamento?->pago_em ?? $charge->pago_em;

                return ['id' => $charge->id, 'entryId' => 'charge-'.$charge->id, 'studentId' => $charge->aluno_id,
                    'student' => $charge->aluno?->nome ?? 'Pagamento recebido',
                    'plan' => $charge->origem === 'evento' ? $charge->descricao : ($charge->assinatura?->plano?->nome ?? $charge->aluno?->plano),
                    'description' => $charge->origem === 'evento' ? 'Inscrição' : 'Mensalidade',
                    'origin' => $charge->origem, 'source' => $source, 'method' => $method,
                    'value' => (float) ($charge->pagamento?->valor ?? $charge->valor),
                    'date' => $date->format('Y-m-d'), 'dueISO' => $charge->vencimento->format('Y-m-d'),
                    'dueDate' => $charge->vencimento->format('d/m/Y'), 'paidAt' => $date->format('d/m/Y H:i'),
                    'status' => 'pago', 'bucket' => 'pago', 'refundReview' => (bool) $charge->refund_review_required,
                    'sentAt' => $charge->enviado_em?->format('d/m/Y H:i'),
                    'demo' => str_starts_with($charge->descricao ?? '', '[DEMO]')];
            });
        $manual = Receita::where('organizacao_id', $organizationId)->whereBetween('competencia', [$start, $end])->get()
            ->map(fn ($entry) => ['id' => 'income-'.$entry->id, 'entryId' => 'income-'.$entry->id, 'studentId' => null,
                'student' => $entry->descricao ?? 'Entrada avulsa', 'plan' => 'Entrada avulsa', 'description' => 'Entrada avulsa',
                'source' => $entry->origem, 'origin' => $entry->origem, 'method' => $entry->metodo,
                'value' => (float) $entry->valor, 'date' => $entry->competencia->format('Y-m-d'),
                'dueISO' => null, 'dueDate' => '—', 'paidAt' => $entry->competencia->format('d/m/Y'),
                'status' => 'pago', 'bucket' => 'pago', 'refundReview' => false, 'sentAt' => null, 'demo' => false]);

        return $charges->concat($manual)->sortByDesc(fn ($row) => $row['date'].' '.$row['entryId'])->values();
    }
}
