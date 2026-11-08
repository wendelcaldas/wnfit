<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Rules\BrazilianPhone;
use App\Services\PublicBookingNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicScheduleController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $organization = $this->organization($slug);
        $today = today();
        $lastDay = $today->copy()->addDays(60);
        $dates = $this->eventsWithSpots($organization, $today, $lastDay)
            ->map(fn (Agendamento $event) => $event->inicio_em->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'organization' => $organization->nome_fantasia,
            'today' => $today->toDateString(),
            'maxDate' => $lastDay->toDateString(),
            'availableDates' => $dates,
        ]);
    }

    public function availability(Request $request, string $slug): JsonResponse
    {
        $organization = $this->organization($slug);
        $data = $request->validate(['date' => $this->dateRules()]);
        $date = Carbon::parse($data['date']);
        $events = $this->eventsWithSpots($organization, $date, $date)
            ->map(fn (Agendamento $event) => [
                ...$this->eventDetails($event),
                'availableSpots' => $this->freeSpots($event),
                'capacity' => $event->capacidade,
            ])->values();

        return response()->json(['date' => $data['date'], 'events' => $events]);
    }

    public function store(Request $request, string $slug, PublicBookingNotificationService $notifications): JsonResponse
    {
        $organization = $this->organization($slug);
        $data = $request->validate([
            'eventId' => ['required', 'integer', 'min:1'],
            'date' => $this->dateRules(),
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new BrazilianPhone],
            'requestId' => ['required', 'uuid'],
        ]);
        $phone = BrazilianPhone::digits($data['phone']);

        return DB::transaction(function () use ($organization, $data, $phone, $notifications) {
            $organization = Organizacao::whereKey($organization->id)->where('ativa', true)->lockForUpdate()->firstOrFail();
            $event = $organization->agendamentos()->find($data['eventId']);
            abort_unless($event && $event->status === 'agendado', 409, 'Este compromisso não está mais disponível.');

            $existing = $event->publicReservations()->where('request_id', $data['requestId'])->first();
            if ($existing) {
                abort_unless($existing->name === $data['name'] && $existing->phone === $phone, 409, 'Esta solicitação já foi utilizada.');

                return response()->json(['reservation' => [...$this->eventDetails($event), 'reference' => $existing->reference]]);
            }

            $date = Carbon::parse($data['date']);
            $bookable = $this->bookableEvents($organization, $date, $date)->whereKey($event->id)->exists();
            abort_unless($bookable && $event->inicio_em->format('H:i') === $data['start'] && $event->fim_em->format('H:i') === $data['end'], 409, 'Este horário mudou ou não está mais disponível.');
            abort_if($event->publicReservations()->where('phone', $phone)->exists(), 409, 'Já existe uma reserva com este telefone para este horário.');

            $occupied = $event->alunos()->wherePivot('status', 'confirmado')->count() + $event->publicReservations()->count();
            abort_if($occupied >= $event->capacidade, 409, 'As vagas deste horário acabaram. Escolha outro compromisso.');

            $reservation = $event->publicReservations()->create([
                'request_id' => $data['requestId'], 'reference' => Str::upper(Str::random(16)), 'name' => $data['name'], 'phone' => $phone,
            ]);
            $notifications->created($event, $reservation, $event->titulo);

            return response()->json(['reservation' => [...$this->eventDetails($event), 'reference' => $reservation->reference]], 201);
        }, 3);
    }

    private function organization(string $slug): Organizacao
    {
        return Organizacao::where('slug', $slug)->where('ativa', true)->firstOrFail();
    }

    private function dateRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(60)->toDateString()];
    }

    private function bookableEvents(Organizacao $organization, Carbon $start, Carbon $end)
    {
        return $organization->agendamentos()
            ->where('publicar_vagas', true)
            ->where('status', 'agendado')
            ->where('inicio_em', '>', now())
            ->whereBetween('inicio_em', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNotNull('capacidade')
            ->whereDoesntHave('publicBooking');
    }

    private function eventsWithSpots(Organizacao $organization, Carbon $start, Carbon $end): Collection
    {
        return $this->bookableEvents($organization, $start, $end)
            ->select(['id', 'titulo', 'inicio_em', 'fim_em', 'capacidade'])
            ->withCount(['alunos as confirmed_count' => fn ($query) => $query->where('agendamento_aluno.status', 'confirmado')])
            ->withCount('publicReservations')
            ->orderBy('inicio_em')
            ->orderBy('id')
            ->get()
            ->filter(fn (Agendamento $event) => $this->freeSpots($event) > 0);
    }

    private function freeSpots(Agendamento $event): int
    {
        return $event->capacidade - $event->confirmed_count - $event->public_reservations_count;
    }

    private function eventDetails(Agendamento $event): array
    {
        return [
            'id' => $event->id, 'title' => $event->titulo, 'date' => $event->inicio_em->toDateString(),
            'start' => $event->inicio_em->format('H:i'), 'end' => $event->fim_em->format('H:i'),
        ];
    }
}
