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
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function publish(Aluno $student, Treino $workout, ?int $publisher, ?string $endsOn = null): StudentWorkoutPlan
    {
        abort_unless($workout->status === 'ativo', 422, 'Publique um treino completo antes de atribuí-lo.');

        return DB::transaction(function () use ($student, $workout, $publisher, $endsOn) {
            Aluno::whereKey($student->id)->lockForUpdate()->firstOrFail();
            StudentWorkoutPlan::where('aluno_id', $student->id)->where('status', 'ativo')->update(['status' => 'encerrado']);
            $student->treinos()->updateExistingPivot($student->treinos()->pluck('treinos.id'), ['ativo' => false]);
            $student->treinos()->syncWithoutDetaching([$workout->id => ['ativo' => true]]);

            return StudentWorkoutPlan::create([
                'aluno_id' => $student->id, 'treino_id' => $workout->id, 'published_by' => $publisher,
                'snapshot' => $this->snapshot($workout), 'starts_on' => today(),
                'ends_on' => $endsOn ?: today()->addWeeks($workout->duracao_semanas)->toDateString(),
            ]);
        });
    }

    public function current(int $studentId): ?StudentWorkoutPlan
    {
        return StudentWorkoutPlan::where('aluno_id', $studentId)->where('status', 'ativo')
            ->whereDate('starts_on', '<=', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->latest('id')->first();
    }

    public function payload(StudentWorkoutPlan $plan): array
    {
        return [...$plan->snapshot, 'assignmentId' => $plan->id, 'status' => $plan->status,
            'startsOn' => $plan->starts_on->toDateString(), 'endsOn' => $plan->ends_on?->toDateString()];
    }
}
