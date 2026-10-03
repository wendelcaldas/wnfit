<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\StudentWorkoutPlan;
use App\Models\Treino;
use App\Services\WorkoutPublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkoutController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $baseQuery = Treino::query()->where('organizacao_id', $organization->id);
        $query = (clone $baseQuery)->with('criador:id,name');

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim()->toString().'%';
            $query->where(fn ($builder) => $builder
                ->where('nome', 'like', $term)
                ->orWhere('objetivo', 'like', $term)
                ->orWhere('descricao', 'like', $term));
        }

        if ($request->filled('status') && $request->status !== 'todos') {
            $query->where('status', $request->status);
        }

        if ($request->filled('objetivo') && $request->objetivo !== 'todos') {
            $query->where('objetivo', $request->objetivo);
        }

        return response()->json([
            'summary' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('status', 'ativo')->count(),
                'drafts' => (clone $baseQuery)->where('status', 'rascunho')->count(),
                'archived' => (clone $baseQuery)->where('status', 'arquivado')->count(),
            ],
            'workouts' => $query->latest('updated_at')->get()->map(fn (Treino $workout) => [
                'id' => $workout->id,
                'name' => $workout->nome,
                'objective' => $workout->objetivo,
                'level' => $workout->nivel,
                'sessionsPerWeek' => $workout->sessoes_semana,
                'durationWeeks' => $workout->duracao_semanas,
                'status' => $workout->status,
                'description' => $workout->descricao,
                'author' => $workout->criador?->name ?? 'Equipe',
                'updatedAt' => $workout->updated_at->diffForHumans(),
            ])->values(),
            'filters' => [
                'objectives' => (clone $baseQuery)->whereNotNull('objetivo')->distinct()->orderBy('objetivo')->pluck('objetivo'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $data = $request->validate($this->rules($request));

        $workout = DB::transaction(function () use ($data, $organization, $request) {
            $workout = Treino::query()->create([
                ...collect($data)->except(['days', 'studentId', 'revision'])->all(),
                'organizacao_id' => $organization->id,
                'criado_por' => $request->user()->id,
            ]);
            $this->syncDays($workout, $data['days']);
            if (! empty($data['studentId']) && $data['status'] === 'ativo') {
                $student = Aluno::query()->findOrFail($data['studentId']);
                app(WorkoutPublishingService::class)->publish($student, $workout, $request->user()->id);
            }

            return $workout;
        });

        return response()->json(['workout' => $this->detail($workout)], 201);
    }

    public function show(Request $request, Treino $workout): JsonResponse
    {
        $this->ensureOrganization($request, $workout);

        return response()->json(['workout' => $this->detail($workout)]);
    }

    public function update(Request $request, Treino $workout): JsonResponse
    {
        $this->ensureOrganization($request, $workout);
        $data = $request->validate($this->rules($request));

        DB::transaction(function () use ($workout, $data, $request) {
            $workout = Treino::whereKey($workout->id)->lockForUpdate()->firstOrFail();
            if (isset($data['revision'])) {
                abort_unless($this->detail($workout)['revision'] === $data['revision'], 409, 'Este treino foi alterado. Recarregue antes de salvar.');
            }
            $workout->update(collect($data)->except(['days', 'studentId', 'revision'])->all());
            $this->syncDays($workout, $data['days']);
            if (! empty($data['studentId']) && $data['status'] === 'ativo') {
                app(WorkoutPublishingService::class)->publish(Aluno::findOrFail($data['studentId']), $workout->fresh(), $request->user()->id);
            }
        });

        return response()->json(['workout' => $this->detail($workout->fresh())]);
    }

    private function rules(Request $request): array
    {
        $organization = $request->user()->organizacoes()->firstOrFail();

        return [
            'revision' => ['sometimes', 'string'],
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['required', 'string', 'max:80'],
            'nivel' => ['required', Rule::in(['iniciante', 'intermediario', 'avancado'])],
            'sessoes_semana' => ['required', 'integer', 'min:1', 'max:7'],
            'duracao_semanas' => ['required', 'integer', 'min:1', 'max:52'],
            'status' => ['required', Rule::in(['rascunho', 'ativo'])],
            'descricao' => ['nullable', 'string'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'studentId' => ['nullable', Rule::exists('alunos', 'id')->where('organizacao_id', $organization->id)],
            'days.*.name' => ['required', 'string', 'max:100'],
            'days.*.focus' => ['nullable', 'string', 'max:120'],
            'days.*.id' => ['nullable', 'integer', 'distinct'],
            'days.*.exercises' => [$request->input('status') === 'ativo' ? 'required' : 'present', 'array', 'min:'.($request->input('status') === 'ativo' ? '1' : '0'), 'max:100'],
            'days.*.exercises.*.id' => ['nullable', 'integer', 'distinct'],
            'days.*.exercises.*.exerciseId' => ['required', Rule::exists('exercicios', 'id')->where(fn ($query) => $query->where('ativo', true)->where(fn ($scope) => $scope->whereNull('organizacao_id')->orWhere('organizacao_id', $organization->id)))],
            'days.*.exercises.*.name' => ['required', 'string', 'max:255'],
            'days.*.exercises.*.muscleGroup' => ['nullable', 'string', 'max:100'],
            'days.*.exercises.*.sets' => ['required', 'integer', 'min:1', 'max:20'],
            'days.*.exercises.*.repetitions' => ['required', 'string', 'max:30'],
            'days.*.exercises.*.load' => ['nullable', 'string', 'max:50'],
            'days.*.exercises.*.restSeconds' => ['required', 'integer', 'min:0', 'max:1800'],
            'days.*.exercises.*.notes' => ['nullable', 'string'],
        ];
    }

    private function syncDays(Treino $workout, array $days): void
    {
        $dayIds = [];
        foreach ($days as $dayIndex => $dayData) {
            $day = ! empty($dayData['id']) ? $workout->dias()->findOrFail($dayData['id']) : $workout->dias()->make();
            $day->fill(['nome' => $dayData['name'], 'foco' => $dayData['focus'] ?? null, 'ordem' => $dayIndex])->save();
            $dayIds[] = $day->id;
            $exerciseIds = [];
            foreach ($dayData['exercises'] as $exerciseIndex => $exercise) {
                $item = ! empty($exercise['id']) ? $day->exercicios()->findOrFail($exercise['id']) : $day->exercicios()->make();
                $item->fill([
                    'exercicio_id' => $exercise['exerciseId'], 'nome' => $exercise['name'], 'grupo_muscular' => $exercise['muscleGroup'] ?? null,
                    'series' => $exercise['sets'], 'repeticoes' => $exercise['repetitions'], 'carga' => $exercise['load'] ?? null,
                    'descanso_segundos' => $exercise['restSeconds'], 'observacoes' => $exercise['notes'] ?? null, 'ordem' => $exerciseIndex,
                ])->save();
                $exerciseIds[] = $item->id;
            }
            $day->exercicios()->whereNotIn('id', $exerciseIds)->delete();
        }
        $workout->dias()->whereNotIn('id', $dayIds)->delete();
    }

    private function ensureOrganization(Request $request, Treino $workout): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($workout->organizacao_id === $organization->id, 404);
    }

    private function detail(Treino $workout): array
    {
        $payload = [...app(WorkoutPublishingService::class)->snapshot($workout), 'status' => $workout->status];

        return [...$payload, 'revision' => hash('sha256', json_encode($payload).$workout->updated_at->toISOString())];
    }

    public function duplicate(Request $request, Treino $workout): JsonResponse
    {
        $this->ensureOrganization($request, $workout);
        $copy = DB::transaction(function () use ($workout, $request) {
            $copy = $workout->replicate();
            $copy->nome = mb_substr($workout->nome, 0, 245).' (cópia)';
            $copy->status = 'rascunho';
            $copy->criado_por = $request->user()->id;
            $copy->save();
            $days = app(WorkoutPublishingService::class)->snapshot($workout)['days'];
            foreach ($days as &$day) {
                unset($day['id']);
                foreach ($day['exercises'] as &$exercise) {
                    unset($exercise['id']);
                }
                unset($exercise);
            }
            unset($day);
            $this->syncDays($copy, $days);

            return $copy;
        });

        return response()->json(['workout' => $this->detail($copy)], 201);
    }

    public function status(Request $request, Treino $workout): JsonResponse
    {
        $this->ensureOrganization($request, $workout);
        $data = $request->validate(['status' => ['required', Rule::in(['arquivado', 'rascunho'])]]);
        $workout->update($data);

        return response()->json(['workout' => $this->detail($workout)]);
    }

    public function personalize(Request $request, Aluno $student, StudentWorkoutPlan $plan): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($student->organizacao_id === $organization->id && $plan->aluno_id === $student->id, 404);
        $snapshot = $plan->snapshot;
        $copy = DB::transaction(function () use ($snapshot, $request, $organization, $student) {
            $copy = Treino::create([
                'organizacao_id' => $organization->id, 'criado_por' => $request->user()->id,
                'nome' => mb_substr($snapshot['name'].' — '.$student->nome, 0, 255),
                'objetivo' => $snapshot['objective'], 'nivel' => $snapshot['level'],
                'sessoes_semana' => $snapshot['sessionsPerWeek'], 'duracao_semanas' => $snapshot['durationWeeks'],
                'descricao' => $snapshot['description'], 'status' => 'rascunho',
            ]);
            $days = $snapshot['days'];
            foreach ($days as &$day) {
                unset($day['id']);
                foreach ($day['exercises'] as &$exercise) {
                    unset($exercise['id']);
                }
                unset($exercise);
            }
            unset($day);
            $this->syncDays($copy, $days);

            return $copy;
        });

        return response()->json(['workout' => $this->detail($copy)], 201);
    }
}
