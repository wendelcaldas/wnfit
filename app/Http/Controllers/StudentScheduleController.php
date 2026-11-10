<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Organizacao;
use App\Services\AppointmentSpots;
use App\Services\PublicBookingNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentScheduleController extends Controller
{
    public function __construct(private AppointmentSpots $spots) {}

    public function index(Request $request): JsonResponse
    {
        $student = $request->user('student')->aluno;
        $organization = $student->organizacao;
        abort_unless($organization->hasModule('schedule'), 404);

        $today = today();
        $lastDay = $this->spots->lastDay();
        $confirmed = fn (Builder $query) => $query->whereKey($student->id)->where('agendamento_aluno.status', 'confirmado');
        $events = $this->spots->upcoming($organization, $today, $lastDay)
            ->where(function (Builder $query) use ($confirmed) {
                $query->where(fn (Builder $published) => $this->spots->published($published))
                    ->orWhereHas('alunos', $confirmed);
            })
            ->withExists(['alunos as confirmed' => $confirmed]);

        $events = $this->spots->withOccupancy($events)
            ->orderBy('inicio_em')
            ->orderBy('id')
            ->get()
            ->map(fn (Agendamento $event) => $this->eventPayload($event, $event->confirmed))
            ->filter(fn (array $event) => $event['confirmed'] || $event['availableSpots'] > 0)
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'maxDate' => $lastDay->toDateString(),
            'events' => $events,
        ]);
    }

    public function store(Request $request, int $eventId, PublicBookingNotificationService $notifications): JsonResponse
    {
        $student = $request->user('student')->aluno;
        $data = $request->validate($this->spots->reservationRules());

        return DB::transaction(function () use ($student, $eventId, $data, $notifications) {
            $organization = Organizacao::whereKey($student->organizacao_id)->where('ativa', true)->lockForUpdate()->firstOrFail();
            abort_unless($organization->hasModule('schedule'), 404);

            $date = Carbon::parse($data['date']);
            $upcoming = $this->spots->upcoming($organization, $date, $date);
            $event = $this->spots->withOccupancy(clone $upcoming)->find($eventId);
            abort_unless($event, 409, 'Este compromisso não está mais disponível.');
            $this->spots->assertUnchanged($event, $data);

            if ($event->alunos()->whereKey($student->id)->wherePivot('status', 'confirmado')->exists()) {
                return response()->json(['event' => $this->eventPayload($event, true)]);
            }

            abort_unless($this->spots->published($upcoming)->whereKey($eventId)->exists(), 409, 'Este compromisso não está mais disponível.');
            $this->spots->assertFreeSpot($event);
            $event->alunos()->syncWithoutDetaching([$student->id => ['status' => 'confirmado']]);
            $notifications->studentBooked($event, $student);

            return response()->json(['event' => $this->eventPayload($event, true)], 201);
        }, 3);
    }

    public function destroy(Request $request, int $eventId): Response
    {
        $student = $request->user('student')->aluno;

        DB::transaction(function () use ($student, $eventId) {
            $organization = Organizacao::whereKey($student->organizacao_id)->where('ativa', true)->lockForUpdate()->firstOrFail();
            abort_unless($organization->hasModule('schedule'), 404);

            $event = $organization->agendamentos()->whereHas('alunos', fn (Builder $query) => $query->whereKey($student->id))
                ->findOrFail($eventId);
            $participants = $event->alunos()->wherePivot('status', 'confirmado');
            if (! $participants->whereKey($student->id)->exists()) {
                return;
            }

            abort_unless($event->status === 'agendado' && $event->inicio_em->isFuture(), 409, 'Este compromisso não pode mais ser cancelado.');
            $participants->updateExistingPivot($student->id, ['status' => 'cancelado']);
        }, 3);

        return response()->noContent();
    }

    private function eventPayload(Agendamento $event, bool $confirmed): array
    {
        return [
            ...$this->spots->details($event),
            'location' => $event->local,
            'modality' => $event->modalidade,
            'confirmed' => $confirmed,
            'availableSpots' => $confirmed ? 0 : $this->spots->freeSpots($event),
        ];
    }
}
