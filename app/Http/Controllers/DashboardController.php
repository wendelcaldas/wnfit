<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Agendamento;
use App\Models\Checkin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $organization = $request->user()->organizacoes()->firstOrFail();
        $start = Carbon::createFromFormat('!Y-m', $data['month'] ?? now()->format('Y-m'));
        $events = Agendamento::query()
            ->where('organizacao_id', $organization->id)
            ->where('status', '!=', 'cancelado')
            ->where('inicio_em', '<', $start->copy()->addMonth())
            ->where('fim_em', '>=', $start)
            ->with('instrutor:id,name')
            ->withCount(['alunos as reservas' => fn ($query) => $query->where('agendamento_aluno.status', 'confirmado')])
            ->orderBy('inicio_em')->get()
            ->map(fn (Agendamento $event) => [
                'id' => $event->id, 'title' => $event->titulo,
                'date' => $event->inicio_em->toDateString(), 'endDate' => $event->fim_em->toDateString(),
                'time' => $event->inicio_em->format('H:i'), 'endTime' => $event->fim_em->format('H:i'),
                'location' => $event->local ?: 'Sem local', 'instructor' => $event->instrutor?->name ?: 'Equipe',
                'participants' => $event->reservas, 'capacity' => $event->capacidade,
                'modality' => $event->modalidade,
            ]);

        return response()->json(['month' => $start->format('Y-m'), 'today' => today()->toDateString(), 'events' => $events]);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $organizationId = $organization->id;
        $today = Carbon::today();

        $activeStudents = Aluno::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', 'ativo')
            ->count();

        $todayCheckins = Checkin::query()
            ->where('organizacao_id', $organizationId)
            ->whereDate('realizado_em', $today)
            ->count();

        $todayClasses = Agendamento::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', '!=', 'cancelado')
            ->whereDate('inicio_em', $today)
            ->count();

        $todayReservations = DB::table('agendamento_aluno')
            ->join('agendamentos', 'agendamentos.id', '=', 'agendamento_aluno.agendamento_id')
            ->where('agendamentos.organizacao_id', $organizationId)
            ->where('agendamentos.status', '!=', 'cancelado')
            ->whereDate('agendamentos.inicio_em', $today)
            ->where('agendamento_aluno.status', 'confirmado')
            ->count();

        return response()->json([
            'stats' => [
                ['label' => 'Alunos ativos', 'value' => (string) $activeStudents, 'caption' => 'Base ativa agora', 'badge' => 'Alunos', 'type' => 'students'],
                ['label' => 'Check-ins hoje', 'value' => (string) $todayCheckins, 'caption' => 'Entradas registradas hoje', 'badge' => 'Frequencia', 'type' => 'checkins'],
                ['label' => 'Aulas hoje', 'value' => (string) $todayClasses, 'caption' => $this->nextClassCaption($organizationId, $today), 'badge' => 'Agenda', 'type' => 'classes'],
                ['label' => 'Reservas hoje', 'value' => (string) $todayReservations, 'caption' => 'Inscrições confirmadas nas aulas de hoje', 'badge' => 'Agenda', 'type' => 'reservations'],
            ],
            'chart' => $this->checkinsByDay($organizationId, $today),
            'classes' => $this->upcomingClasses($organizationId, $today),
            'clients' => $this->recentClients($organizationId),
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
        return Agendamento::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', '!=', 'cancelado')
            ->whereDate('inicio_em', $today)
            ->withCount(['alunos as reservas' => fn ($query) => $query->where('agendamento_aluno.status', 'confirmado')])
            ->with('instrutor:id,name')
            ->orderBy('inicio_em')
            ->limit(4)
            ->get()
            ->map(fn (Agendamento $event) => [
                'time' => $event->inicio_em->format('H:i'),
                'name' => $event->titulo,
                'room' => trim(($event->local ?: 'Sem local') . ' - ' . ($event->instrutor?->name ?: 'Equipe')),
                'slots' => $event->capacidade ? "{$event->reservas}/{$event->capacidade}" : "{$event->reservas} aluno(s)",
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

    private function nextClassCaption(int $organizationId, Carbon $today): string
    {
        $nextClass = Agendamento::query()
            ->where('organizacao_id', $organizationId)
            ->where('status', '!=', 'cancelado')
            ->whereDate('inicio_em', $today)
            ->where('inicio_em', '>=', now())
            ->orderBy('inicio_em')
            ->first();

        if (! $nextClass) {
            return 'Sem proximas aulas hoje';
        }

        return 'Proxima: '.$nextClass->inicio_em->format('H:i').' '.$nextClass->titulo;
    }
}
