<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Rules\BrazilianPhone;
use App\Services\AppointmentSpots;
use App\Services\PublicBookingNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicScheduleController extends Controller
{
    public function __construct(private AppointmentSpots $spots) {}

    public function show(string $slug): JsonResponse
    {
        $organization = $this->organization($slug);
        $today = today();
        $lastDay = $this->spots->lastDay();
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
        $data = $request->validate(['date' => $this->spots->dateRules()]);
        $date = Carbon::parse($data['date']);
        $events = $this->eventsWithSpots($organization, $date, $date)
            ->map(fn (Agendamento $event) => [
                ...$this->spots->details($event),
                'availableSpots' => $this->spots->freeSpots($event),
                'capacity' => $event->capacidade,
            ])->values();

        return response()->json(['date' => $data['date'], 'events' => $events]);
    }

    public function store(Request $request, string $slug, PublicBookingNotificationService $notifications): JsonResponse
    {
        $organization = $this->organization($slug);
        $data = $request->validate([
            'eventId' => ['required', 'integer', 'min:1'],
            ...$this->spots->reservationRules(),
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new BrazilianPhone],
            'requestId' => ['required', 'uuid'],
        ]);
        $phone = BrazilianPhone::digits($data['phone']);

        return DB::transaction(function () use ($organization, $data, $phone, $notifications) {
            $organization = Organizacao::whereKey($organization->id)->where('ativa', true)->lockForUpdate()->firstOrFail();
            abort_unless($organization->hasModule('schedule'), 404);
            $event = $this->spots->withOccupancy(Agendamento::where('organizacao_id', $organization->id))->find($data['eventId']);
            abort_unless($event && $event->status === 'agendado', 409, 'Este compromisso não está mais disponível.');

            $existing = $event->publicReservations()->where('request_id', $data['requestId'])->first();
            if ($existing) {
                abort_unless($existing->name === $data['name'] && $existing->phone === $phone, 409, 'Esta solicitação já foi utilizada.');

                return response()->json(['reservation' => [...$this->spots->details($event), 'reference' => $existing->reference]]);
            }

            $date = Carbon::parse($data['date']);
            $bookable = $this->spots->published($this->spots->upcoming($organization, $date, $date))->whereKey($event->id)->exists();
            abort_unless($bookable, 409, 'Este horário mudou ou não está mais disponível.');
            $this->spots->assertUnchanged($event, $data);
            abort_if($event->publicReservations()->where('phone', $phone)->exists(), 409, 'Já existe uma reserva com este telefone para este horário.');

            $this->spots->assertFreeSpot($event);

            $reservation = $event->publicReservations()->create([
                'request_id' => $data['requestId'], 'reference' => Str::upper(Str::random(16)), 'name' => $data['name'], 'phone' => $phone,
            ]);
            $notifications->created($event, $reservation, $event->titulo);

            return response()->json(['reservation' => [...$this->spots->details($event), 'reference' => $reservation->reference]], 201);
        }, 3);
    }

    private function organization(string $slug): Organizacao
    {
        $organization = Organizacao::where('slug', $slug)->where('ativa', true)->firstOrFail();
        abort_unless($organization->hasModule('schedule'), 404);

        return $organization;
    }

    private function eventsWithSpots(Organizacao $organization, Carbon $start, Carbon $end): Collection
    {
        $events = $this->spots->published($this->spots->upcoming($organization, $start, $end))
            ->select(['id', 'titulo', 'inicio_em', 'fim_em', 'capacidade']);

        return $this->spots->withOccupancy($events)
            ->orderBy('inicio_em')
            ->orderBy('id')
            ->get()
            ->filter(fn (Agendamento $event) => $this->spots->freeSpots($event) > 0);
    }
}
