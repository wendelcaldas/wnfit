<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Organizacao;
use App\Models\Unidade;
use App\Services\OrganizationUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationUnitController extends Controller
{
    private function manager(Request $request): bool
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        return in_array($org->usuarios()->whereKey($request->user()->id)->first()?->pivot?->papel, ['proprietario', 'administrador'], true);
    }

    public function index(Request $request, OrganizationUnitService $units): JsonResponse
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        $units->defaultFor($org->id);
        return response()->json(['canManage' => $this->manager($request), 'units' => Unidade::where('organizacao_id', $org->id)
            ->withCount('alunos')->orderByDesc('padrao')->orderByDesc('ativa')->orderBy('nome')->get()->map(fn ($unit) => [
                'id' => $unit->id, 'name' => $unit->nome, 'active' => $unit->ativa, 'default' => $unit->padrao, 'students' => $unit->alunos_count,
            ])]);
    }

    public function save(Request $request, OrganizationUnitService $units, ?Unidade $unit = null): JsonResponse
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        abort_unless($this->manager($request), 403);
        if ($unit) abort_unless($unit->organizacao_id === $org->id, 404);
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('unidades', 'nome')->where('organizacao_id', $org->id)->ignore($unit?->id)],
            'active' => ['required', 'boolean'], 'default' => ['required', 'boolean'],
        ]);
        $created = ! $unit;
        $units->defaultFor($org->id);
        DB::transaction(function () use ($org, $data, &$unit) {
            Organizacao::whereKey($org->id)->lockForUpdate()->firstOrFail();
            if ($unit) $unit = Unidade::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if (($data['default'] && ! $data['active']) || ($unit?->padrao && (! $data['active'] || ! $data['default']))) {
                throw ValidationException::withMessages(['default' => 'Mantenha uma unidade padrão ativa. Para desativar esta unidade, defina outra como padrão primeiro.']);
            }
            if ($data['default']) Unidade::where('organizacao_id', $org->id)->update(['padrao' => false]);
            $unit ??= new Unidade(['organizacao_id' => $org->id]);
            $unit->fill(['nome' => $data['name'], 'ativa' => $data['active'], 'padrao' => $data['default']])->save();
            Aluno::where('organizacao_id', $org->id)->where('unidade_id', $unit->id)->update(['unidade' => $unit->nome]);
        });
        return response()->json(['id' => $unit->id], $created ? 201 : 200);
    }

    public function destroy(Request $request, Unidade $unit): JsonResponse
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        abort_unless($this->manager($request), 403);
        abort_unless($unit->organizacao_id === $org->id, 404);
        $data = $request->validate([
            'destination_id' => ['nullable', 'integer', Rule::exists('unidades', 'id')->where('organizacao_id', $org->id)->where('ativa', true), Rule::notIn([$unit->id])],
        ]);
        $transferred = DB::transaction(function () use ($org, $unit, $data) {
            Organizacao::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $unit = Unidade::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($unit->padrao) {
                throw ValidationException::withMessages(['unit' => 'Defina outra unidade como padrão antes de excluir esta.']);
            }
            $students = Aluno::where('organizacao_id', $org->id)->where('unidade_id', $unit->id);
            $count = (clone $students)->count();
            if ($count) {
                $destination = Unidade::where('organizacao_id', $org->id)->where('ativa', true)
                    ->where('id', '!=', $unit->id)->whereKey($data['destination_id'] ?? null)->lockForUpdate()->first();
                if (! $destination) {
                    throw ValidationException::withMessages(['destination_id' => 'Selecione a unidade que receberá os alunos antes de excluir.']);
                }
                $students->update(['unidade_id' => $destination->id, 'unidade' => $destination->nome]);
            }
            $unit->delete();
            return $count;
        });
        return response()->json(['transferredStudents' => $transferred]);
    }
}
