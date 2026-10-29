<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceController extends Controller
{
    public function __invoke(Request $request, BillingService $billing, \App\Services\FinanceIncomeService $income): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:todos,previsto,aberto,pendente,atrasado,pago,hoje,proximos'],
            'page' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'in:todos,direto,totalpass,wellhub'],
            'movements' => ['nullable', 'boolean'],
        ]);
        $organization = $request->user()->organizacoes()->firstOrFail();
        $start = Carbon::createFromFormat('!Y-m', $data['month'] ?? now()->format('Y-m'));
        $end = $start->copy()->endOfMonth();
        $billing->refreshOverdueCharges($organization->id, $end);
        $base = Cobranca::query()->where('organizacao_id', $organization->id);

        $today = today()->format('Y-m-d');
        $received = $income->entries($organization->id, $start, $end);
        $unpaid = (clone $base)->with(['aluno', 'assinatura.plano'])->whereBetween('vencimento', [$start, $end])
            ->where('status', '!=', 'pago')->get()->map(fn ($charge) => [
                'id' => $charge->id, 'studentId' => $charge->aluno_id, 'student' => $charge->aluno?->nome,
                'plan' => $charge->origem === 'evento' ? $charge->descricao : ($charge->assinatura?->plano?->nome ?? $charge->aluno?->plano),
                'origin' => $charge->origem, 'source' => in_array($charge->origem, ['wellhub', 'totalpass']) ? $charge->origem : 'direto',
                'value' => (float) $charge->valor, 'dueISO' => $charge->vencimento->format('Y-m-d'),
                'date' => $charge->vencimento->format('Y-m-d'), 'dueDate' => $charge->vencimento->format('d/m/Y'),
                'status' => $charge->status, 'bucket' => $charge->status, 'refundReview' => (bool) $charge->refund_review_required,
                'sentAt' => $charge->enviado_em?->format('d/m/Y H:i'), 'paidAt' => null,
            ]);
        // Every amount belongs to exactly one bucket. Receipts use payment date; outstanding items use due date.
        $rows = $received->concat($unpaid)->values();
        $upcoming = $rows->where('bucket', 'pendente');
        $overdue = $rows->where('bucket', 'atrasado');
        $receivedTotal = round((float) $received->sum('value'), 2);
        $openTotal = round((float) $upcoming->sum('value'), 2);
        $overdueTotal = round((float) $overdue->sum('value'), 2);
        $status = $data['status'] ?? 'todos';
        $source = $data['source'] ?? 'todos';
        $list = $rows->filter(function ($row) use ($status, $source, $data, $today) {
            if ($source !== 'todos' && ($source === 'direto' ? in_array($row['source'], ['wellhub', 'totalpass']) : $row['source'] !== $source)) {
                return false;
            }
            if (! empty($data['q']) && mb_stripos($row['student'] ?? '', $data['q']) === false) {
                return false;
            }

            return match ($status) {
                'previsto' => $row['bucket'] !== 'cancelado',
                'aberto', 'pendente' => $row['bucket'] === 'pendente',
                'atrasado' => $row['bucket'] === 'atrasado',
                'pago' => $row['bucket'] === 'pago',
                'hoje' => $row['bucket'] === 'pendente' && $row['dueISO'] === $today,
                'proximos' => $row['bucket'] === 'pendente' && $row['dueISO'] >= $today && $row['dueISO'] <= today()->addDays(7)->format('Y-m-d'),
                default => true,
            };
        })->sortBy(fn ($row) => match ($row['bucket']) { 'atrasado' => '0', 'pendente' => '1', default => '2' }.' '.$row['date'].' '.$row['id'])->values();
        $page = (int) ($data['page'] ?? 1);
        $daily = collect(range(1, $start->daysInMonth))->map(function ($day) use ($start, $received, $upcoming, $overdue) {
            $date = $start->copy()->day($day)->format('Y-m-d');
            $paid = $received->where('date', $date);
            $pending = $upcoming->where('dueISO', $date);
            $late = $overdue->where('dueISO', $date);

            return ['date' => $date, 'value' => round((float) $paid->sum('value'), 2),
                'paid' => round((float) $paid->sum('value'), 2),
                'pending' => round((float) $pending->sum('value') + (float) $late->sum('value'), 2),
                'upcoming' => round((float) $pending->sum('value'), 2), 'overdue' => round((float) $late->sum('value'), 2),
                'paidCount' => $paid->count(), 'pendingCount' => $pending->count() + $late->count()];
        });

        return response()->json([
            'summary' => [
                'received' => $receivedTotal, 'open' => $openTotal, 'overdue' => $overdueTotal,
                'expected' => round($receivedTotal + $openTotal + $overdueTotal, 2),
                'recovered' => (float) $received->filter(fn ($row) => $row['dueISO'] && $row['dueISO'] < $start->format('Y-m-d'))->sum('value'),
                'current' => (float) $received->filter(fn ($row) => $row['dueISO'] && $row['dueISO'] >= $start->format('Y-m-d') && $row['dueISO'] <= $end->format('Y-m-d'))->sum('value'),
                'advance' => (float) $received->filter(fn ($row) => $row['dueISO'] && $row['dueISO'] > $end->format('Y-m-d'))->sum('value'),
                'manual' => (float) $received->whereNull('dueISO')->sum('value'),
            ],
            'dashboard' => [
                'daily' => $daily,
                'settlement' => ['paid' => $receivedTotal, 'pending' => $openTotal, 'overdue' => $overdueTotal],
                'counts' => ['all' => $rows->count(), 'paid' => $received->count(), 'open' => $upcoming->count(),
                    'overdue' => $overdue->count(), 'today' => $upcoming->where('dueISO', $today)->count()],
            ],
            'charges' => $list->forPage($page, 20)->values(), 'listTotal' => round((float) $list->sum('value'), 2),
            'pagination' => ['page' => $page, 'lastPage' => max(1, (int) ceil($list->count() / 20)), 'total' => $list->count()],
        ]);
    }
}
