<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Models\PublicScheduleBooking;
use App\Rules\BrazilianPhone;
use App\Services\PublicBookingNotificationService;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicBookingController extends Controller
{
    public function show(string $token, ScheduleAvailabilityService $availability)
    {
        $s = $availability->publicSettings($token);
        $org = Organizacao::findOrFail($s->organizacao_id);

        return response()->json(['organization' => $org->nome_fantasia, 'title' => $s->title, 'durationMinutes' => $s->duration_minutes, 'timezone' => config('app.timezone'),
            'instructorName' => $org->usuarios()->whereKey($s->instructor_id)->value('name'), 'location' => $s->location, 'today' => today()->toDateString(), 'maxDate' => today()->addDays(60)->toDateString(),
            'availableDays' => collect($s->weekly_hours)->filter(fn ($ranges) => count($ranges) > 0)->keys()->values()]);
    }

    public function availability(Request $r, string $token, ScheduleAvailabilityService $availability)
    {
        $s = $availability->publicSettings($token);
        $data = $r->validate(['date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:'.today()->addDays(60)->toDateString()]);

        return response()->json(['date' => $data['date'], 'slots' => $availability->slots($s, $data['date'])]);
    }

    private function payload(PublicScheduleBooking $booking): array
    {
        $event = Agendamento::findOrFail($booking->agendamento_id);

        return ['reference' => $booking->reference, 'date' => $event->inicio_em->toDateString(), 'startsAt' => $event->inicio_em->toIso8601String(), 'endsAt' => $event->fim_em->toIso8601String(), 'title' => $event->titulo, 'status' => $event->status];
    }

    public function store(Request $r, string $token, ScheduleAvailabilityService $availability)
    {
        $s = $availability->publicSettings($token);
        $r->merge(['name' => trim((string) $r->input('name'))]);
        $data = $r->validate(['startsAt' => 'required|date', 'name' => 'required|string|max:120', 'phone' => ['required', 'string', new BrazilianPhone], 'email' => 'nullable|email|max:255', 'requestId' => 'required|uuid']);
        $data['phone'] = preg_replace('/\D/', '', $data['phone']);
        $start = Carbon::parse($data['startsAt'])->setTimezone(config('app.timezone'));
        $created = false;
        $booking = DB::transaction(function () use ($s, $token, $availability, $data, $start, &$created) {
            Organizacao::whereKey($s->organizacao_id)->lockForUpdate()->firstOrFail();
            $s = $availability->publicSettings($token);
            $existing = PublicScheduleBooking::where('organizacao_id', $s->organizacao_id)->where('request_id', $data['requestId'])->first();
            if ($existing) {
                $event = Agendamento::findOrFail($existing->agendamento_id);
                abort_unless($existing->booking_link_id === $s->booking_link_id && $existing->booking_token === $token && $event->inicio_em->eq($start) && $existing->name === $data['name'] && $existing->phone === $data['phone'] && $existing->email === ($data['email'] ?? null), 409, 'Esta solicitação já foi utilizada. Recarregue os horários.');

                return $existing;
            }
            $slot = collect($availability->slots($s, $start->toDateString()))->first(fn ($slot) => Carbon::parse($slot['startsAt'])->eq($start));
            abort_unless($slot, 409, 'Este horário não está mais disponível. Escolha outro horário.');
            $event = Agendamento::create(['organizacao_id' => $s->organizacao_id, 'titulo' => $s->title.' · '.$data['name'], 'tipo' => 'individual', 'status' => 'agendado',
                'inicio_em' => $start, 'fim_em' => Carbon::parse($slot['endsAt'])->setTimezone(config('app.timezone')), 'instrutor_id' => $s->instructor_id, 'modalidade' => 'presencial', 'local' => $s->location, 'capacidade' => 1]);
            $created = true;

            $booking = PublicScheduleBooking::create(['organizacao_id' => $s->organizacao_id, 'agendamento_id' => $event->id, 'request_id' => $data['requestId'],
                'booking_link_id' => $s->booking_link_id, 'booking_token' => $token, 'reference' => Str::upper(Str::random(16)), 'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null]);
            app(PublicBookingNotificationService::class)->created($event, $booking, $s->title);

            return $booking;
        });

        return response()->json(['booking' => $this->payload($booking)], $created ? 201 : 200);
    }
}
