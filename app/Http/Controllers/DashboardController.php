<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Aula;
use App\Models\Checkin;
use App\Models\Cobranca;
use App\Models\Receita;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $organizationId = $organization->id;
        $this->billing->refreshOverdueCharges($organizationId);
        $today = Carbon::today();

        $activeStudents = Aluno::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', 'ativo')
            ->count();

        $todayCheckins = Checkin::query()
            ->where('organizacao_id', $organizationId)
            ->whereDate('realizado_em', $today)
            ->count();

        $todayClasses = Aula::query()
            ->where('organizacao_id', $organizationId)
            ->whereDate('data', $today)
            ->count();

        $monthRevenue = (float) Cobranca::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', 'pago')
            ->whereBetween('pago_em', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->sum('valor');

        return response()->json([
            'stats' => [
                ['label' => 'Clientes ativos', 'value' => (string) $activeStudents, 'caption' => 'Base ativa agora', 'badge' => 'Crescimento', 'type' => 'students'],
                ['label' => 'Check-ins hoje', 'value' => (string) $todayCheckins, 'caption' => 'Entradas registradas hoje', 'badge' => 'Frequencia', 'type' => 'checkins'],
                ['label' => 'Aulas hoje', 'value' => (string) $todayClasses, 'caption' => $this->nextClassCaption($organizationId, $today), 'badge' => 'Agenda', 'type' => 'classes'],
                ['label' => 'Faturamento', 'value' => 'R$ '.number_format($monthRevenue, 2, ',', '.'), 'caption' => 'Competencia atual', 'badge' => 'Receita', 'type' => 'revenue'],
            ],
            'chart' => $this->checkinsByDay($organizationId, $today),
            'classes' => $this->upcomingClasses($organizationId, $today),
            'clients' => $this->recentClients($organizationId),
            'incomeBars' => $this->incomeByMonth($organizationId, $today),
            'organization' => [
                'name' => $organization->nome_fantasia,
            ],
        ]);
    }

    private function checkinsByDay(int $organizationId, Carbon $today): array
    {
        $start = $today->copy()->subDays(6)->startOfDay();
        $end = $today->copy()->endOfDay();

        $rows = Checkin::query()
            ->selectRaw('date(realizado_em) as day, count(*) as total')
            ->where('organizacao_id', $organizationId)
            ->whereBetween('realizado_em', [$start, $end])
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, 6))->map(function (int $offset) use ($start, $rows) {
            $date = $start->copy()->addDays($offset);

            return [
                'day' => $date->translatedFormat('d M'),
                'value' => (int) ($rows[$date->toDateString()] ?? 0),
            ];
        })->all();
    }

    private function upcomingClasses(int $organizationId, Carbon $today): array
    {
        return Aula::query()
            ->where('organizacao_id', $organizationId)
            ->whereDate('data', $today)
            ->orderBy('hora')
            ->limit(4)
            ->get()
            ->map(fn (Aula $aula) => [
                'time' => Carbon::parse($aula->hora)->format('H:i'),
                'name' => $aula->nome,
                'room' => trim(($aula->sala ?: 'Sala') . ' - ' . ($aula->instrutor ?: 'Equipe')),
                'slots' => "{$aula->reservas}/{$aula->capacidade}",
                'dot' => 'bg-slate-400',
            ])
            ->all();
    }

    private function recentClients(int $organizationId): array
    {
        return Aluno::query()
            ->where('organizacao_id', $organizationId)
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Aluno $student) => [
                'initials' => collect(explode(' ', $student->nome))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''),
                'name' => $student->nome,
                'phone' => $student->telefone ?: '-',
                'plan' => $student->plano,
                'dueDate' => optional($student->vencimento)->format('d/m/Y') ?: '-',
                'status' => ucfirst($student->status),
            ])
            ->all();
    }

    private function incomeByMonth(int $organizationId, Carbon $today): array
    {
        $start = $today->copy()->subMonths(5)->startOfMonth();
        $rows = Cobranca::query()
            ->selectRaw("strftime('%Y-%m', pago_em) as month, sum(valor) as total")
            ->where('organizacao_id', $organizationId)
            ->where('status', 'pago')
            ->where('pago_em', '>=', $start)
            ->groupBy('month')
            ->pluck('total', 'month');

        $values = collect(range(0, 5))->map(function (int $offset) use ($start, $rows) {
            $date = $start->copy()->addMonths($offset);

            return [
                'label' => ucfirst($date->translatedFormat('M')),
                'raw' => (float) ($rows[$date->format('Y-m')] ?? 0),
            ];
        });

        $max = max($values->max('raw'), 1);

        return $values->map(fn ($item) => [
            'label' => $item['label'],
            'value' => max(12, (int) round(($item['raw'] / $max) * 92)),
        ])->all();
    }

    private function nextClassCaption(int $organizationId, Carbon $today): string
    {
        $nextClass = Aula::query()
            ->where('organizacao_id', $organizationId)
            ->whereDate('data', $today)
            ->where('hora', '>=', now()->format('H:i:s'))
            ->orderBy('hora')
            ->first();

        if (! $nextClass) {
            return 'Sem proximas aulas hoje';
        }

        return 'Proxima: '.Carbon::parse($nextClass->hora)->format('H:i').' '.$nextClass->nome;
    }
}
