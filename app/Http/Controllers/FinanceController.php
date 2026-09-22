<?php

namespace App\Http\Controllers;

use App\Models\Assinatura;
use App\Models\Cobranca;
use App\Models\Pagamento;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    public function __invoke(Request $request, BillingService $billing): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:todos,aberto,pendente,atrasado,pago,hoje,proximos'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $organization = $request->user()->organizacoes()->firstOrFail();
        $billing->refreshOverdueCharges($organization->id);
        $start = Carbon::createFromFormat('!Y-m', $data['month'] ?? now()->format('Y-m'));
        $end = $start->copy()->endOfMonth();
        $base = Cobranca::query()->where('organizacao_id', $organization->id);
        $monthCharges = (clone $base)->whereBetween('vencimento', [$start, $end])->get();
        $open = $monthCharges->whereIn('status', ['pendente', 'atrasado']);

        // Project only missing monthly installments; existing charges retain their actual value.
        $subscriptions = Assinatura::query()->with('plano')
            ->where('organizacao_id', $organization->id)->where('status', 'ativa')
            ->whereDate('inicio_em', '<=', $end)->get();
        $projected = $subscriptions->filter(function ($subscription) use ($monthCharges, $start, $end) {
            if ($monthCharges->contains('assinatura_id', $subscription->id) || ! $subscription->proximo_vencimento) {
                return false;
            }

            $anchor = $subscription->recorrencia_inicio ?? $subscription->proximo_vencimento;
            $months = match ($subscription->plano?->ciclo) {
                'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12, default => null,
            };
            $distance = ($start->year - $anchor->year) * 12 + $start->month - $anchor->month;
            if (! $months || $distance < 0 || $distance % $months !== 0 || $subscription->aluno?->status === 'pausado') {
                return false;
            }
            $due = $anchor->copy()->addMonthsNoOverflow($distance);
            if (($subscription->pausa_em && $due->gte($subscription->pausa_em)) || ($subscription->encerramento_em && $due->gte($subscription->encerramento_em))) {
                return false;
            }

            return $subscription->proximo_vencimento->between($start, $end)
                || ($subscription->auto_renovacao && $start->gte(today()->startOfMonth()));
        })->sum(fn ($subscription) => (float) $subscription->plano?->valor_mensal);

        $query = (clone $base)->with(['aluno', 'assinatura.plano'])
            ->whereBetween('vencimento', [$start, $end]);
        if (! empty($data['q'])) {
            $query->whereHas('aluno', fn ($q) => $q->where('nome', 'like', '%'.$data['q'].'%'));
        }
        $status = $data['status'] ?? 'todos';
        if (in_array($status, ['aberto', 'hoje', 'proximos'])) {
            $query->whereIn('status', ['pendente', 'atrasado']);
            if ($status === 'hoje') {
                $query->whereDate('vencimento', today());
            } elseif ($status === 'proximos') {
                $query->whereBetween('vencimento', [today(), today()->addDays(7)]);
            }
        } elseif ($status !== 'todos') {
            $query->where('status', $status);
        }
        $charges = $query->orderByRaw("CASE WHEN status = 'atrasado' THEN 0 WHEN status = 'pendente' THEN 1 ELSE 2 END")
            ->orderBy('vencimento')->orderBy('id')->paginate(20);
        $payments = Pagamento::query()->whereHas('cobranca', fn ($q) => $q->where('organizacao_id', $organization->id))
            ->whereBetween('pago_em', [$start, $end]);

        return response()->json([
            'summary' => [
                'recovered' => (float) (clone $payments)->whereHas('cobranca', fn ($q) => $q->whereDate('vencimento', '<', $start))->sum('valor'),
                'current' => (float) (clone $payments)->whereHas('cobranca', fn ($q) => $q->whereBetween('vencimento', [$start, $end]))->sum('valor'),
                'advance' => (float) (clone $payments)->whereHas('cobranca', fn ($q) => $q->whereDate('vencimento', '>', $end))->sum('valor'),
                'expected' => round((float) $monthCharges->where('status', '!=', 'cancelado')->sum('valor') + $projected, 2),
                'projected' => round($projected, 2),
                'received' => (float) Pagamento::query()->whereHas('cobranca', fn ($q) => $q->where('organizacao_id', $organization->id))
                    ->whereBetween('pago_em', [$start, $end])->sum('valor'),
                'open' => (float) $open->sum('valor'),
                'overdue' => (float) $open->where('status', 'atrasado')->sum('valor'),
            ],
            'charges' => $charges->getCollection()->map(fn ($charge) => [
                'id' => $charge->id,
                'studentId' => $charge->aluno_id,
                'student' => $charge->aluno->nome,
                'plan' => $charge->assinatura?->plano?->nome ?? $charge->aluno->plano,
                'value' => (float) $charge->valor,
                'dueDate' => $charge->vencimento->format('d/m/Y'),
                'status' => $charge->status,
                'sentAt' => $charge->enviado_em?->format('d/m/Y H:i'),
                'paidAt' => $charge->pago_em?->format('d/m/Y H:i'),
            ]),
            'pagination' => ['page' => $charges->currentPage(), 'lastPage' => $charges->lastPage(), 'total' => $charges->total()],
        ]);
    }
}
