<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Assinatura;
use App\Models\Plano;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationPlanController extends Controller
{
    private function manager(Request $request): bool
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        $role = $org->usuarios()->whereKey($request->user()->id)->first()?->pivot?->papel;

        return in_array($role, ['proprietario', 'administrador'], true);
    }

    public function index(Request $request): JsonResponse
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        $plans = Plano::where('organizacao_id', $org->id)->orderByDesc('ativo')->orderBy('nome')->get();
        $counts = Assinatura::where('organizacao_id', $org->id)->selectRaw('plano_id, count(*) as total')->groupBy('plano_id')->pluck('total', 'plano_id');

        return response()->json(['canManage' => $this->manager($request), 'plans' => $plans->map(fn ($plan) => [
            'id' => $plan->id, 'name' => $plan->nome, 'value' => (float) $plan->valor_mensal,
            'cycle' => $plan->ciclo, 'active' => $plan->ativo, 'subscriptions' => (int) ($counts[$plan->id] ?? 0),
        ])]);
    }

    public function save(Request $request, ?Plano $plan = null): JsonResponse
    {
        $org = $request->user()->organizacoes()->firstOrFail();
        abort_unless($this->manager($request), 403);
        if ($plan) {
            abort_unless($plan->organizacao_id === $org->id, 404);
        }
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('planos', 'nome')->where('organizacao_id', $org->id)->ignore($plan?->id)],
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'cycle' => ['required', Rule::in(['mensal', 'trimestral', 'semestral', 'anual'])],
            'active' => ['required', 'boolean'],
        ]);
        $created = ! $plan;
        DB::transaction(function () use (&$plan, $org, $data) {
            if ($plan) {
                $plan = Plano::whereKey($plan->id)->lockForUpdate()->firstOrFail();
                if ($data['cycle'] !== $plan->ciclo && Assinatura::where('plano_id', $plan->id)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['cycle' => 'Este plano já tem assinaturas. Crie outro plano para oferecer um ciclo diferente.']);
                }
                Aluno::where('organizacao_id', $org->id)->whereHas('assinatura', fn ($q) => $q->where('plano_id', $plan->id))->update(['plano' => $data['name']]);
            } else {
                $plan = new Plano(['organizacao_id' => $org->id]);
            }
            $plan->fill(['nome' => $data['name'], 'valor_mensal' => $data['value'], 'ciclo' => $data['cycle'], 'ativo' => $data['active']])->save();
        });

        return response()->json(['id' => $plan->id], $created ? 201 : 200);
    }
}
