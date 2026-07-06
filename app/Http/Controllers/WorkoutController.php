<?php

namespace App\Http\Controllers;

use App\Models\Treino;
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
                ...collect($data)->except('days')->all(),
                'organizacao_id' => $organization->id,
                'criado_por' => $request->user()->id,
            ]);
            $this->syncDays($workout, $data['days']);
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

        DB::transaction(function () use ($workout, $data) {
            $workout->update(collect($data)->except('days')->all());
            $workout->dias()->delete();
            $this->syncDays($workout, $data['days']);
        });

        return response()->json(['workout' => $this->detail($workout->fresh())]);
    }

    private function rules(Request $request): array
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        return [
            'nome' => ['required', 'string', 'max:255'],
            'objetivo' => ['required', 'string', 'max:80'],
            'nivel' => ['required', Rule::in(['iniciante', 'intermediario', 'avancado'])],
            'sessoes_semana' => ['required', 'integer', 'min:1', 'max:7'],
            'duracao_semanas' => ['required', 'integer', 'min:1', 'max:52'],
            'status' => ['required', Rule::in(['rascunho', 'ativo'])],
            'descricao' => ['nullable', 'string'],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*.name' => ['required', 'string', 'max:100'],
            'days.*.focus' => ['nullable', 'string', 'max:120'],
            'days.*.exercises' => ['required', 'array', 'min:1'],
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
        foreach ($days as $dayIndex => $dayData) {
            $day = $workout->dias()->create(['nome' => $dayData['name'], 'foco' => $dayData['focus'] ?? null, 'ordem' => $dayIndex]);
            foreach ($dayData['exercises'] as $exerciseIndex => $exercise) {
                $day->exercicios()->create([
                    'exercicio_id' => $exercise['exerciseId'], 'nome' => $exercise['name'], 'grupo_muscular' => $exercise['muscleGroup'] ?? null,
                    'series' => $exercise['sets'], 'repeticoes' => $exercise['repetitions'], 'carga' => $exercise['load'] ?? null,
                    'descanso_segundos' => $exercise['restSeconds'], 'observacoes' => $exercise['notes'] ?? null, 'ordem' => $exerciseIndex,
                ]);
            }
        }
    }

    private function ensureOrganization(Request $request, Treino $workout): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($workout->organizacao_id === $organization->id, 404);
    }

    private function detail(Treino $workout): array
    {
        $workout->load(['criador:id,name', 'dias.exercicios']);
        return [
            'id' => $workout->id, 'name' => $workout->nome, 'objective' => $workout->objetivo,
            'level' => $workout->nivel, 'sessionsPerWeek' => $workout->sessoes_semana,
            'durationWeeks' => $workout->duracao_semanas, 'status' => $workout->status,
            'description' => $workout->descricao,
            'days' => $workout->dias->map(fn ($day) => [
                'name' => $day->nome, 'focus' => $day->foco,
                'exercises' => $day->exercicios->map(fn ($exercise) => [
                    'exerciseId' => $exercise->exercicio_id, 'name' => $exercise->nome, 'muscleGroup' => $exercise->grupo_muscular,
                    'sets' => $exercise->series, 'repetitions' => $exercise->repeticoes,
                    'load' => $exercise->carga, 'restSeconds' => $exercise->descanso_segundos,
                    'notes' => $exercise->observacoes,
                ])->values(),
            ])->values(),
        ];
    }
}
