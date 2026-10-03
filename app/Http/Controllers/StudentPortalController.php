<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\StudentWorkoutSession;
use App\Services\WorkoutPublishingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentPortalController extends Controller
{
    public function home(WorkoutPublishingService $service)
    {
        $studentId = Auth::guard('student')->user()->aluno_id;
        $plan = $service->current($studentId);
        $sessions = StudentWorkoutSession::where('aluno_id', $studentId)->latest('id')->limit(30)->get();

        return response()->json([
            'plan' => $plan ? $service->payload($plan) : null,
            'activeSession' => $sessions->firstWhere('status', 'in_progress'),
            'history' => $sessions->where('status', 'completed')->values(),
            'weekCompleted' => StudentWorkoutSession::where('aluno_id', $studentId)->where('status', 'completed')->where('finished_at', '>=', now()->startOfWeek())->count(),
        ]);
    }

    public function start(Request $request, WorkoutPublishingService $service)
    {
        $data = $request->validate(['dayIndex' => ['required', 'integer', 'min:0', 'max:6']]);
        $studentId = Auth::guard('student')->user()->aluno_id;
        $session = DB::transaction(function () use ($studentId, $service, $data) {
            Aluno::whereKey($studentId)->lockForUpdate()->firstOrFail();
            $active = StudentWorkoutSession::where('aluno_id', $studentId)->where('status', 'in_progress')->first();
            if ($active) {
                return $active;
            }
            $plan = $service->current($studentId);
            abort_unless($plan, 422, 'Você ainda não tem uma ficha vigente. Fale com seu professor.');
            $day = $plan->snapshot['days'][$data['dayIndex']] ?? null;
            abort_unless($day && count($day['exercises']) > 0, 422, 'Este dia não possui exercícios.');

            return StudentWorkoutSession::create([
                'aluno_id' => $studentId, 'student_workout_plan_id' => $plan->id, 'day_index' => $data['dayIndex'],
                'snapshot' => ['planName' => $plan->snapshot['name'], ...$day], 'progress' => [], 'started_at' => now(),
            ]);
        });

        return response()->json(['session' => $this->sessionPayload($session)]);
    }

    public function show(StudentWorkoutSession $session)
    {
        $this->authorizeSession($session);

        return response()->json(['session' => $this->sessionPayload($session)]);
    }

    public function update(Request $request, StudentWorkoutSession $session)
    {
        $this->authorizeSession($session);
        $data = $request->validate([
            'revision' => ['sometimes', 'string'],
            'progress' => ['present', 'array', 'list', 'max:2000'], 'progress.*' => ['array:exerciseIndex,setIndex,done,load,repetitions'],
            'progress.*.exerciseIndex' => ['required', 'integer', 'min:0'], 'progress.*.setIndex' => ['required', 'integer', 'min:0'],
            'progress.*.done' => ['required', 'boolean'], 'progress.*.load' => ['nullable', 'string', 'max:50'],
            'progress.*.repetitions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'finish' => ['sometimes', 'boolean'], 'abandon' => ['sometimes', 'boolean'],
        ]);
        $keys = [];
        foreach ($data['progress'] as $item) {
            $exercise = $session->snapshot['exercises'][$item['exerciseIndex']] ?? null;
            $key = $item['exerciseIndex'].':'.$item['setIndex'];
            abort_unless($exercise && $item['setIndex'] < $exercise['sets'] && ! isset($keys[$key]), 422, 'Série inválida ou repetida.');
            $keys[$key] = true;
        }
        $session = DB::transaction(function () use ($session, $data, $request) {
            $locked = StudentWorkoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            // Repeated finish requests return the same completed session; history is never overwritten.
            if ($locked->status !== 'in_progress') {
                abort_unless($request->boolean('finish') || $request->boolean('abandon'), 409, 'Esta sessão já foi encerrada. Carregue o registro salvo.');

                return $locked;
            }
            if (isset($data['revision'])) {
                abort_unless($this->sessionPayload($locked)['revision'] === $data['revision'], 409, 'Esta sessão foi alterada em outra tela. Carregue o registro salvo antes de continuar.');
            }
            if ($request->boolean('finish')) {
                abort_unless(collect($data['progress'])->contains(fn ($item) => $item['done']), 422, 'Conclua ao menos uma série antes de finalizar.');
            }
            $locked->update([
                'progress' => $data['progress'],
                'status' => $request->boolean('abandon') ? 'abandoned' : ($request->boolean('finish') ? 'completed' : 'in_progress'),
                'finished_at' => ($request->boolean('finish') || $request->boolean('abandon')) ? now() : null,
            ]);

            return $locked;
        });

        return response()->json(['session' => $this->sessionPayload($session)]);
    }

    private function sessionPayload(StudentWorkoutSession $session): array
    {
        $session->refresh();

        return [...$session->toArray(), 'revision' => hash('sha256', json_encode($session->progress).$session->status.$session->updated_at->toISOString())];
    }

    private function authorizeSession(StudentWorkoutSession $session): void
    {
        abort_unless($session->aluno_id === Auth::guard('student')->user()->aluno_id, 404);
    }
}
