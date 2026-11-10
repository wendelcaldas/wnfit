<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Organizacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AppointmentSpots
{
    public function lastDay(): Carbon
    {
        return today()->addDays(60);
    }

    public function reservationRules(): array
    {
        return [
            'date' => $this->dateRules(),
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
        ];
    }

    public function dateRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$this->lastDay()->toDateString()];
    }

    public function upcoming(Organizacao $organization, Carbon $start, Carbon $end): Builder
    {
        return Agendamento::query()
            ->where('organizacao_id', $organization->id)
            ->where('status', 'agendado')
            ->where('inicio_em', '>', now())
            ->whereBetween('inicio_em', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
    }

    public function published(Builder $events): Builder
    {
        return $events->where('publicar_vagas', true)
            ->whereNotNull('capacidade')
            ->whereDoesntHave('publicBooking');
    }

    public function withOccupancy(Builder $events): Builder
    {
        return $events
            ->withCount(['alunos as confirmed_count' => fn ($query) => $query->where('agendamento_aluno.status', 'confirmado')])
            ->withCount('publicReservations');
    }

    public function freeSpots(Agendamento $event): int
    {
        return max(0, $event->capacidade - $event->confirmed_count - $event->public_reservations_count);
    }

    public function details(Agendamento $event): array
    {
        return [
            'id' => $event->id, 'title' => $event->titulo, 'date' => $event->inicio_em->toDateString(),
            'start' => $event->inicio_em->format('H:i'), 'end' => $event->fim_em->format('H:i'),
        ];
    }

    public function assertUnchanged(Agendamento $event, array $data): void
    {
        $details = $this->details($event);
        abort_unless(
            $details['date'] === $data['date'] && $details['start'] === $data['start'] && $details['end'] === $data['end'],
            409,
            'Este horário mudou ou não está mais disponível.'
        );
    }

    public function assertFreeSpot(Agendamento $event): void
    {
        abort_if($this->freeSpots($event) === 0, 409, 'As vagas deste horário acabaram. Escolha outro compromisso.');
    }
}
