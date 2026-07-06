<?php

namespace App\Http\Controllers;

use App\Models\Exercicio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $query = Exercicio::query()
            ->where('ativo', true)
            ->where(fn ($builder) => $builder->whereNull('organizacao_id')->orWhere('organizacao_id', $organization->id));

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim()->toString().'%';
            $query->where(fn ($builder) => $builder->where('nome', 'like', $term)->orWhere('grupo_muscular', 'like', $term)->orWhere('equipamento', 'like', $term));
        }
        if ($request->filled('muscle') && $request->muscle !== 'todos') $query->where('grupo_muscular', $request->muscle);
        if ($request->filled('equipment') && $request->equipment !== 'todos') $query->where('equipamento', $request->equipment);

        return response()->json([
            'exercises' => $query->orderBy('nome')->limit(100)->get()->map(fn (Exercicio $exercise) => $this->payload($exercise)),
            'filters' => [
                'muscles' => Exercicio::query()->where('ativo', true)->where(fn ($q) => $q->whereNull('organizacao_id')->orWhere('organizacao_id', $organization->id))->distinct()->orderBy('grupo_muscular')->pluck('grupo_muscular'),
                'equipment' => Exercicio::query()->where('ativo', true)->where(fn ($q) => $q->whereNull('organizacao_id')->orWhere('organizacao_id', $organization->id))->whereNotNull('equipamento')->distinct()->orderBy('equipamento')->pluck('equipamento'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'muscleGroup' => ['required', 'string', 'max:100'],
            'secondaryMuscle' => ['nullable', 'string', 'max:100'], 'equipment' => ['nullable', 'string', 'max:100'],
            'category' => ['required', Rule::in(['musculacao', 'funcional', 'mobilidade', 'cardio'])],
            'level' => ['required', Rule::in(['todos', 'iniciante', 'intermediario', 'avancado'])],
            'instructions' => ['nullable', 'string'], 'safetyNotes' => ['nullable', 'string'],
        ]);

        $exercise = Exercicio::query()->create([
            'organizacao_id' => $organization->id, 'nome' => $data['name'], 'grupo_muscular' => $data['muscleGroup'],
            'grupo_secundario' => $data['secondaryMuscle'] ?? null, 'equipamento' => $data['equipment'] ?? null,
            'categoria' => $data['category'], 'nivel' => $data['level'], 'instrucoes' => $data['instructions'] ?? null,
            'cuidados' => $data['safetyNotes'] ?? null, 'origem' => 'organizacao',
        ]);

        return response()->json(['exercise' => $this->payload($exercise)], 201);
    }

    private function payload(Exercicio $exercise): array
    {
        return [
            'id' => $exercise->id, 'name' => $exercise->nome, 'muscleGroup' => $exercise->grupo_muscular,
            'secondaryMuscle' => $exercise->grupo_secundario, 'equipment' => $exercise->equipamento,
            'category' => $exercise->categoria, 'level' => $exercise->nivel, 'instructions' => $exercise->instrucoes,
            'safetyNotes' => $exercise->cuidados, 'imageUrl' => $exercise->imagem_url, 'videoUrl' => $exercise->video_url,
            'custom' => $exercise->organizacao_id !== null,
        ];
    }
}
