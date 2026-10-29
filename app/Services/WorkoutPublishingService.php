<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\StudentWorkoutPlan;
use App\Models\Treino;
use Illuminate\Support\Facades\DB;

class WorkoutPublishingService
{
    public function snapshot(Treino $workout): array
    {
        $workout->refresh();
        $workout->load(['criador', 'dias.exercicios.exercicio']);

        return [
            'id' => $workout->id, 'name' => $workout->nome, 'objective' => $workout->objetivo,
            'level' => $workout->nivel, 'sessionsPerWeek' => $workout->sessoes_semana,
            'durationWeeks' => $workout->duracao_semanas, 'description' => $workout->descricao,
            'author' => $workout->criador?->name ?? 'Equipe',
            'days' => $workout->dias->map(fn ($day) => [
                'id' => $day->id, 'name' => $day->nome, 'focus' => $day->foco, 'exercisesCount' => $day->exercicios->count(),
                'exercises' => $day->exercicios->map(fn ($item) => [
                    'id' => $item->id, 'exerciseId' => $item->exercicio_id, 'name' => $item->nome,
                    'muscleGroup' => $item->grupo_muscular, 'sets' => $item->series, 'repetitions' => $item->repeticoes,
                    'load' => $item->carga, 'restSeconds' => $item->descanso_segundos, 'notes' => $item->observacoes,
                    'instructions' => $item->exercicio?->instrucoes, 'safetyNotes' => $item->exercicio?->cuidados,
                    'imageUrl' => $item->exercicio?->imagem_url, 'videoUrl' => $item->exercicio?->video_url,
                    'custom' => $item->exercicio ? $item->exercicio->organizacao_id !== null : false,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function publish(Aluno $student, Treino $workout, ?int $publisher, ?string $endsOn = null): StudentWorkoutPlan
    {
        abort_unless($workout->status === 'ativo', 422, 'Publique um treino completo antes de atribuí-lo.');

        return $this->replaceAssignment($student, $workout->id, $this->snapshot($workout), $publisher, $endsOn);
    }

    public function renew(Aluno $student, StudentWorkoutPlan $plan, ?int $publisher, ?string $endsOn = null): StudentWorkoutPlan
    {
        abort_unless($plan->aluno_id === $student->id, 404);

        return $this->replaceAssignment($student, $plan->treino_id, $plan->snapshot, $publisher, $endsOn);
    }

    private function replaceAssignment(Aluno $student, ?int $workoutId, array $snapshot, ?int $publisher, ?string $endsOn): StudentWorkoutPlan
    {
        $weeks = filter_var($snapshot['durationWeeks'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 52],
        ]);
        abort_unless($endsOn || $weeks !== false, 422, 'Defina uma duração válida antes de renovar esta ficha.');

        return DB::transaction(function () use ($student, $workoutId, $snapshot, $publisher, $endsOn, $weeks) {
            Aluno::whereKey($student->id)->lockForUpdate()->firstOrFail();
            StudentWorkoutPlan::where('aluno_id', $student->id)->where('status', 'ativo')->update(['status' => 'encerrado']);
            $student->treinos()->updateExistingPivot($student->treinos()->pluck('treinos.id'), ['ativo' => false]);
            if ($workoutId) {
                $student->treinos()->syncWithoutDetaching([$workoutId => ['ativo' => true]]);
            }

            return StudentWorkoutPlan::create([
                'aluno_id' => $student->id, 'treino_id' => $workoutId, 'published_by' => $publisher,
                'status' => 'ativo',
                'snapshot' => $snapshot, 'starts_on' => today(),
                'ends_on' => $endsOn ?: today()->addWeeks($weeks)->toDateString(),
            ]);
        });
    }

    public function current(int $studentId): ?StudentWorkoutPlan
    {
        $plan = $this->latest($studentId);

        return $plan?->accessStatus() === 'available' ? $plan : null;
    }

    public function latest(int $studentId): ?StudentWorkoutPlan
    {
        // Choose the latest published assignment before checking dates; an expired one
        // must not reactivate an older active record imported from legacy data.
        $query = StudentWorkoutPlan::where('aluno_id', $studentId);

        return (clone $query)->where('status', 'ativo')->latest('id')->first()
            ?? $query->latest('id')->first();
    }

    public function access(?StudentWorkoutPlan $plan): array
    {
        $status = $plan?->accessStatus() ?? 'none';

        return [
            'status' => $status, 'startsOn' => $plan?->starts_on?->toDateString(),
            'endsOn' => $plan?->effectiveEndsOn()?->toDateString(), 'expiresAt' => $plan?->expiresAt(),
            'message' => match ($status) {
                'expired' => 'A validade da sua ficha terminou. Fale com seu professor para renovar o treino.',
                'closed' => 'Esta ficha foi substituída ou encerrada. Consulte seus treinos ou fale com seu professor.',
                'scheduled' => 'Sua ficha ainda não está disponível. Aguarde a data de início definida pelo professor.',
                'unavailable' => 'Sua ficha precisa ser revisada. Fale com seu professor.',
                'none' => 'Seu professor precisa publicar uma ficha vigente para você.',
                default => null,
            },
        ];
    }

    public function payload(StudentWorkoutPlan $plan): array
    {
        return [...$plan->snapshot, 'assignmentId' => $plan->id, 'status' => $plan->status,
            'startsOn' => $plan->starts_on->toDateString(), 'endsOn' => $plan->effectiveEndsOn()?->toDateString(),
            'accessStatus' => $plan->accessStatus(), 'expiresAt' => $plan->expiresAt()];
    }
}
