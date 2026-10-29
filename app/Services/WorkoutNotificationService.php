<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\StaffNotification;
use App\Models\StudentWorkoutPlan;
use App\Models\StudentWorkoutSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $recipients = $this->recipients($members, $student);
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

    public function syncDeadlines(int $organizationId): int
    {
        $organization = Organizacao::whereKey($organizationId)->where('ativa', true)->first();
        if (! $organization) {
            return 0;
        }
        $members = $organization->usuarios()->wherePivot('status', 'ativo')->get();
        $created = 0;
        StudentWorkoutPlan::query()->with('student')
            ->where('status', 'ativo')
            ->whereIn('aluno_id', Aluno::where('organizacao_id', $organizationId)->select('id'))
            ->whereDate('starts_on', '<=', today())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '<=', today()))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('student_workout_plans as newer')
                    ->whereColumn('newer.aluno_id', 'student_workout_plans.aluno_id')
                    ->where('newer.status', 'ativo')
                    ->whereColumn('newer.id', '>', 'student_workout_plans.id');
            })
            ->chunkById(100, function ($plans) use ($members, $organizationId, &$created) {
                $existing = StaffNotification::where('organizacao_id', $organizationId)
                    ->whereIn('student_workout_plan_id', $plans->pluck('id'))
                    ->whereIn('type', ['workout_plan_ends_today', 'workout_plan_expired'])
                    ->get(['user_id', 'student_workout_plan_id', 'type'])
                    ->mapWithKeys(fn ($item) => [$item->user_id.':'.$item->student_workout_plan_id.':'.$item->type => true])->all();
                foreach ($plans as $plan) {
                    $end = $plan->effectiveEndsOn();
                    if (! $end || $end->gt(today())) {
                        continue;
                    }
                    $type = $end->isToday() ? 'workout_plan_ends_today' : 'workout_plan_expired';
                    $student = $plan->student;
                    if (! $student) {
                        continue;
                    }
                    $data = [
                        'studentId' => $student->id, 'studentName' => $student->nome,
                        'assignmentId' => $plan->id, 'workoutName' => $plan->snapshot['name'] ?? 'Treino',
                        'endsOn' => $end->toDateString(), 'expiresAt' => $plan->expiresAt(),
                        'href' => '/alunos/'.$student->id.'?tab=workouts',
                    ];
                    foreach ($this->recipients($members, $student)->unique('id') as $recipient) {
                        $key = $recipient->id.':'.$plan->id.':'.$type;
                        if (isset($existing[$key])) {
                            continue;
                        }
                        $notification = StaffNotification::firstOrCreate([
                            'organizacao_id' => $organizationId, 'user_id' => $recipient->id,
                            'student_workout_plan_id' => $plan->id, 'type' => $type,
                        ], ['data' => $data]);
                        $existing[$key] = true;
                        $created += (int) $notification->wasRecentlyCreated;
                    }
                }
            });

        return $created;
    }

    private function recipients(Collection $members, Aluno $student): Collection
    {
        $recipients = $members->filter(fn ($user) => in_array($user->pivot->papel, ['proprietario', 'administrador'], true));
        // Legacy assignments store the teacher name. Never guess between homonyms or other organizations.
        $teachers = $members->filter(fn ($user) => $user->pivot->papel === 'professor' && trim($user->name) === trim((string) $student->treinador));
        if (filled($student->treinador) && $teachers->count() === 1) {
            $recipients = $recipients->concat($teachers);
        }

        return $recipients;
    }
}
