<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Aluno;
use App\Models\Organizacao;
use App\Services\ScheduleAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        [$organization, $role] = $this->agendaContext($request);
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'instructor_id' => ['nullable', 'integer'],
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $data['start'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end'])->endOfDay();

        $events = $this->eventsQuery($organization, $role, $request->user()->id)
            ->with(['instrutor:id,name', 'alunos:id,nome', 'publicBooking'])
            ->when($data['instructor_id'] ?? null, fn ($query, $instructorId) => $query->where('instrutor_id', $instructorId))
            ->where('inicio_em', '<=', $end)
            ->where('fim_em', '>=', $start)
            ->orderBy('inicio_em')
            ->get();

        return response()->json([
            'operatingHours' => app(ScheduleAvailabilityService::class)->hoursPayload(app(ScheduleAvailabilityService::class)->settings($organization->id)),
            'events' => $events->map(fn (Agendamento $event) => $this->payload($event))->values(),
            'summary' => [
                'events' => $events->where('status', '!=', 'cancelado')->count(),
                'participants' => $events->sum(fn (Agendamento $event) => $event->alunos->where('pivot.status', 'confirmado')->count() + ($event->publicBooking ? 1 : 0)),
                'availableSlots' => $events->where('status', '!=', 'cancelado')->sum(function (Agendamento $event) {
                    return $event->capacidade === null ? 0 : max($event->capacidade - $event->alunos->where('pivot.status', 'confirmado')->count() - ($event->publicBooking ? 1 : 0), 0);
                }),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        [$organization, $role] = $this->agendaContext($request);
        $userId = $request->user()->id;

        $instructors = $organization->usuarios()
            ->wherePivot('status', 'ativo')
            ->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])
            ->when($role === 'professor', fn ($query) => $query->whereKey($userId))
            ->orderBy('name')
            ->get(['users.id', 'users.name'])
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
            ->values();

        return response()->json([
            'instructors' => $instructors,
            'students' => Aluno::query()
                ->where('organizacao_id', $organization->id)
                ->whereIn('status', ['ativo', 'avaliacao'])
                ->orderBy('nome')
                ->get(['id', 'nome', 'telefone'])
                ->map(fn (Aluno $student) => ['id' => $student->id, 'name' => $student->nome, 'phone' => $student->telefone])
                ->values(),
            'role' => $role,
            'operatingHours' => app(ScheduleAvailabilityService::class)->hoursPayload(app(ScheduleAvailabilityService::class)->settings($organization->id)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        [$organization, $role] = $this->agendaContext($request);
        $data = $this->validatedData($request);
        $this->assertRelationsBelongToOrganization($organization, $data, $role, $request->user()->id);
        $occurrences = $this->recurringOccurrences($data);
        $events = DB::transaction(function () use ($organization, $data, $occurrences) {
            Organizacao::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            foreach ($occurrences as $occurrence) {
                if ($data['status'] !== 'cancelado') {
                    app(ScheduleAvailabilityService::class)->assertOpen($organization->id, $occurrence['starts_at'], $occurrence['ends_at']);
                }
                $this->ensureNoInstructorConflict($organization, [...$data, ...$occurrence]);
            }
            $seriesId = count($occurrences) > 1 ? (string) Str::uuid() : null;

            return collect($occurrences)->map(function (array $occurrence) use ($organization, $data, $seriesId) {
                $event = Agendamento::query()->create([
                    ...$this->eventFields([...$data, ...$occurrence]),
                    'organizacao_id' => $organization->id,
                    'serie_id' => $seriesId,
                    'recorrencia_frequencia' => $seriesId ? $data['repeat_frequency'] : null,
                    'recorrencia_ate' => $seriesId ? $data['repeat_until'] : null,
                ]);
                $this->syncParticipants($event, $data['participant_ids'] ?? []);

                return $event;
            });
        });

        $event = $events->first()->load(['instrutor:id,name', 'alunos:id,nome', 'publicBooking']);

        return response()->json([
            'event' => $this->payload($event),
            'createdCount' => $events->count(),
        ], 201);
    }

    public function update(Request $request, Agendamento $schedule): JsonResponse
    {
        [$organization, $role] = $this->agendaContext($request);
        $this->ensureEventAccess($schedule, $organization, $role, $request->user()->id);
        $data = $this->validatedData($request);
        $this->assertRelationsBelongToOrganization($organization, $data, $role, $request->user()->id);
        DB::transaction(function () use ($organization, $schedule, $data) {
            Organizacao::whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $schedule->refresh();
            $unchanged = $schedule->status !== 'cancelado'
                && $schedule->inicio_em->equalTo(Carbon::parse($data['starts_at'])->timezone(config('app.timezone')))
                && $schedule->fim_em->equalTo(Carbon::parse($data['ends_at'])->timezone(config('app.timezone')));
            if ($data['status'] !== 'cancelado' && ! $unchanged) {
                app(ScheduleAvailabilityService::class)->assertOpen($organization->id, Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']));
            }
            $this->ensureNoInstructorConflict($organization, $data, $schedule->id);
            $schedule->update($this->eventFields($data));
            $this->syncParticipants($schedule, $data['participant_ids'] ?? []);
        });

        return response()->json(['event' => $this->payload($schedule->fresh(['instrutor:id,name', 'alunos:id,nome', 'publicBooking']))]);
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['individual', 'coletiva', 'tematica', 'personalizada'])],
            'status' => ['required', Rule::in(['agendado', 'concluido', 'cancelado'])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'instructor_id' => ['nullable', 'integer'],
            'modality' => ['required', Rule::in(['presencial', 'externo', 'online'])],
            'location' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'participant_ids' => ['present', 'array', 'max:1000'],
            'participant_ids.*' => ['integer', 'distinct'],
            'repeat_frequency' => ['sometimes', Rule::in(['none', 'weekly', 'biweekly', 'monthly'])],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['repeat_frequency'] = $data['repeat_frequency'] ?? 'none';
        if ($data['repeat_frequency'] !== 'none' && empty($data['repeat_until'])) {
            throw ValidationException::withMessages(['repeat_until' => 'Informe ate quando o agendamento deve se repetir.']);
        }

        return $data;
    }

    private function assertRelationsBelongToOrganization(Organizacao $organization, array &$data, string $role, int $userId): void
    {
        if ($role === 'professor') {
            $data['instructor_id'] = $userId;
        }

        if (($data['instructor_id'] ?? null) && ! $organization->usuarios()
            ->whereKey($data['instructor_id'])
            ->wherePivot('status', 'ativo')
            ->wherePivotIn('papel', ['proprietario', 'administrador', 'professor'])
            ->exists()) {
            throw ValidationException::withMessages(['instructor_id' => 'Selecione um professor ativo desta organizacao.']);
        }

        $participantIds = $data['participant_ids'] ?? [];
        $validStudents = Aluno::query()->where('organizacao_id', $organization->id)->whereIn('id', $participantIds)->count();
        if ($validStudents !== count($participantIds)) {
            throw ValidationException::withMessages(['participant_ids' => 'Um ou mais alunos nao pertencem a esta organizacao.']);
        }

        if (($data['capacity'] ?? null) !== null && count($participantIds) > $data['capacity']) {
            throw ValidationException::withMessages(['capacity' => 'A capacidade nao pode ser menor que o numero de alunos incluidos.']);
        }
    }

    private function ensureNoInstructorConflict(Organizacao $organization, array $data, ?int $ignoreId = null): void
    {
        if (! ($data['instructor_id'] ?? null) || $data['status'] === 'cancelado') {
            return;
        }

        $conflict = Agendamento::query()
            ->where('organizacao_id', $organization->id)
            ->where('instrutor_id', $data['instructor_id'])
            ->where('status', '!=', 'cancelado')
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('inicio_em', '<', Carbon::parse($data['ends_at']))
            ->where('fim_em', '>', Carbon::parse($data['starts_at']))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages(['starts_at' => 'Este professor ja possui um agendamento neste horario.']);
        }
    }

    private function syncParticipants(Agendamento $event, array $participantIds): void
    {
        $event->alunos()->sync(collect($participantIds)->mapWithKeys(fn ($id) => [$id => ['status' => 'confirmado']])->all());
    }

    private function eventFields(array $data): array
    {
        return [
            'titulo' => $data['title'], 'tipo' => $data['type'], 'status' => $data['status'],
            'inicio_em' => Carbon::parse($data['starts_at'])->timezone(config('app.timezone')), 'fim_em' => Carbon::parse($data['ends_at'])->timezone(config('app.timezone')), 'instrutor_id' => $data['instructor_id'] ?? null,
            'modalidade' => $data['modality'], 'local' => $data['location'] ?? null, 'endereco' => $data['address'] ?? null,
            'capacidade' => $data['capacity'] ?? null, 'observacoes' => $data['notes'] ?? null,
        ];
    }

    private function recurringOccurrences(array $data): array
    {
        $start = Carbon::parse($data['starts_at'])->timezone(config('app.timezone'));
        $end = Carbon::parse($data['ends_at'])->timezone(config('app.timezone'));
        if ($data['repeat_frequency'] === 'none') {
            return [['starts_at' => $start, 'ends_at' => $end]];
        }

        $until = Carbon::parse($data['repeat_until'])->endOfDay();
        $durationInSeconds = $start->diffInSeconds($end);
        $occurrences = [];
        $current = $start->copy();

        while ($current->lessThanOrEqualTo($until)) {
            $occurrences[] = ['starts_at' => $current->copy(), 'ends_at' => $current->copy()->addSeconds($durationInSeconds)];
            if (count($occurrences) > 104) {
                throw ValidationException::withMessages(['repeat_until' => 'Para criar mais de 104 ocorrencias, reduza o periodo ou configure a serie em etapas.']);
            }

            $current = match ($data['repeat_frequency']) {
                'weekly' => $current->addWeek(),
                'biweekly' => $current->addWeeks(2),
                'monthly' => $current->addMonthNoOverflow(),
            };
        }

        return $occurrences;
    }

    private function eventsQuery(Organizacao $organization, string $role, int $userId)
    {
        return Agendamento::query()
            ->where('organizacao_id', $organization->id)
            ->when($role === 'professor', fn ($query) => $query->where('instrutor_id', $userId));
    }

    private function ensureEventAccess(Agendamento $event, Organizacao $organization, string $role, int $userId): void
    {
        abort_unless($event->organizacao_id === $organization->id, 404);
        abort_unless($role !== 'professor' || $event->instrutor_id === $userId, 403);
    }

    private function agendaContext(Request $request): array
    {
        $organization = $request->user()->organizacoes()->where('organizacoes.ativa', true)->wherePivot('status', 'ativo')->firstOrFail();
        $role = $organization->usuarios()->whereKey($request->user()->id)->firstOrFail()->pivot->papel;
        abort_unless(in_array($role, ['proprietario', 'administrador', 'professor'], true), 403);

        return [$organization, $role];
    }

    private function payload(Agendamento $event): array
    {
        $participants = $event->alunos->map(fn (Aluno $student) => [
            'id' => $student->id, 'name' => $student->nome, 'status' => $student->pivot->status,
        ])->values();

        return [
            'id' => $event->id, 'title' => $event->titulo, 'type' => $event->tipo, 'status' => $event->status,
            'startsAt' => $event->inicio_em->toIso8601String(), 'endsAt' => $event->fim_em->toIso8601String(),
            'seriesId' => $event->serie_id, 'recurrenceFrequency' => $event->recorrencia_frequencia,
            'recurrenceUntil' => $event->recorrencia_ate?->toDateString(),
            'instructorId' => $event->instrutor_id, 'instructorName' => $event->instrutor?->name,
            'modality' => $event->modalidade, 'location' => $event->local, 'address' => $event->endereco,
            'capacity' => $event->capacidade, 'notes' => $event->observacoes, 'participants' => $participants,
            'publicBooking' => $event->publicBooking ? $event->publicBooking->only(['name', 'phone', 'email', 'reference']) : null,
            'participantCount' => $participants->where('status', 'confirmado')->count() + ($event->publicBooking ? 1 : 0),
        ];
    }
}
