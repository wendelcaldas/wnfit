<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\StudentPhysicalAssessment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentPhysicalAssessmentController extends Controller
{
    private function authorizeStudent(Request $request, Aluno $student): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($student->organizacao_id === $organization->id, 404);
    }

    private function query(Aluno $student)
    {
        return StudentPhysicalAssessment::where('organizacao_id', $student->organizacao_id)->where('aluno_id', $student->id);
    }

    private function payload(StudentPhysicalAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'assessed_on' => $assessment->assessed_on->toDateString(),
            'name' => $assessment->name,
            'measures' => collect(StudentPhysicalAssessment::MEASURES)->mapWithKeys(fn ($key) => [$key => $assessment->$key === null ? null : (float) $assessment->$key])->all(),
            'method' => $assessment->method,
            'notes' => $assessment->notes,
            'created_by' => $assessment->creator?->name,
            'updated_by' => $assessment->editor?->name,
            'updated_at' => $assessment->updated_at->toIso8601String(),
        ];
    }

    public function index(Request $request, Aluno $student): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        return response()->json(['assessments' => $this->query($student)->with(['creator', 'editor'])->orderByDesc('assessed_on')->orderByDesc('id')->get()->map(fn ($item) => $this->payload($item))]);
    }

    public function show(Request $request, Aluno $student, StudentPhysicalAssessment $assessment): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        abort_unless($assessment->aluno_id === $student->id && $assessment->organizacao_id === $student->organizacao_id, 404);
        $previous = $this->query($student)->where(function ($query) use ($assessment) {
            $query->where('assessed_on', '<', $assessment->assessed_on->toDateString())
                ->orWhere(fn ($sameDay) => $sameDay->whereDate('assessed_on', $assessment->assessed_on)->where('id', '<', $assessment->id));
        })->with(['creator', 'editor'])->orderByDesc('assessed_on')->orderByDesc('id')->first();
        return response()->json(['assessment' => $this->payload($assessment->load(['creator', 'editor'])), 'previous' => $previous ? $this->payload($previous) : null]);
    }

    private function validated(Request $request): array
    {
        $rules = ['name' => ['nullable', 'string', 'max:120'], 'assessed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'method' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:5000']];
        foreach (StudentPhysicalAssessment::MEASURES as $key) {
            $rules[$key] = ['nullable', 'numeric', 'decimal:0,2', $key === 'body_fat' ? 'min:0' : 'gt:0', 'max:'.match ($key) { 'body_fat' => 100, 'height' => 300, 'weight' => 500, default => 400 }];
        }
        $data = $request->validate($rules);
        if (!collect(StudentPhysicalAssessment::MEASURES)->contains(fn ($key) => isset($data[$key]))) {
            throw ValidationException::withMessages(['measures' => 'Informe pelo menos uma medida.']);
        }
        // Updates replace the complete measurement form; omitted fields become empty.
        return [...array_fill_keys(StudentPhysicalAssessment::MEASURES, null), 'name' => null, 'method' => null, 'notes' => null, ...$data];
    }

    public function store(Request $request, Aluno $student): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        $assessment = StudentPhysicalAssessment::create([...$this->validated($request), 'organizacao_id' => $student->organizacao_id, 'aluno_id' => $student->id, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        return response()->json(['assessment' => $this->payload($assessment->load(['creator', 'editor']))], 201);
    }

    public function update(Request $request, Aluno $student, StudentPhysicalAssessment $assessment): JsonResponse
    {
        $this->authorizeStudent($request, $student);
        abort_unless($assessment->aluno_id === $student->id && $assessment->organizacao_id === $student->organizacao_id, 404);
        $assessment->update([...$this->validated($request), 'updated_by' => $request->user()->id]);
        return response()->json(['assessment' => $this->payload($assessment->load(['creator', 'editor']))]);
    }
}
