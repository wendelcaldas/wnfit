<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StudentWorkoutSession;

class WorkoutNotificationService
{
    public function completed(StudentWorkoutSession $session): void
    {
        if ($session->status !== 'completed') {
            return;
        }
        $student = Aluno::findOrFail($session->aluno_id);
        $organization = Organizacao::whereKey($student->organizacao_id)->where('ativa', true)->first();
        if (! $organization) {
            return;
        }
        $members = $organization->usuarios()->wherePivot('status', 'ativo')->get();
        $recipients = $members->filter(fn ($user) => in_array($user->pivot->papel, ['proprietario', 'administrador'], true));
        // Legacy assignments store the teacher name. Never guess between homonyms or other organizations.
        $teachers = $members->filter(fn ($user) => $user->pivot->papel === 'professor' && trim($user->name) === trim((string) $student->treinador));
        if (filled($student->treinador) && $teachers->count() === 1) {
            $recipients = $recipients->concat($teachers);
        }
        $exercises = $session->snapshot['exercises'] ?? [];
        $completed = collect($session->progress)->where('done', true);
        $completedExercises = 0;
        foreach ($exercises as $index => $exercise) {
            if ($completed->where('exerciseIndex', $index)->count() >= (int) $exercise['sets']) {
                $completedExercises++;
            }
        }
        $data = [
            'studentId' => $student->id, 'studentName' => $student->nome,
            'sessionId' => $session->id, 'workoutName' => $session->snapshot['planName'] ?? 'Treino',
            'dayName' => $session->snapshot['name'] ?? 'Treino', 'focus' => $session->snapshot['focus'] ?? null,
            'completedAt' => $session->finished_at->toISOString(),
            'durationSeconds' => max(0, (int) $session->started_at->diffInSeconds($session->finished_at, false)),
            'completedExercises' => $completedExercises, 'totalExercises' => count($exercises),
            'completedSets' => $completed->count(), 'totalSets' => collect($exercises)->sum('sets'),
            'href' => '/alunos/'.$student->id.'?tab=workouts',
        ];
        foreach ($recipients->unique('id') as $recipient) {
            StaffNotification::firstOrCreate([
                'organizacao_id' => $organization->id, 'user_id' => $recipient->id,
                'student_workout_session_id' => $session->id, 'type' => 'workout_completed',
            ], ['data' => $data]);
        }
    }
}
