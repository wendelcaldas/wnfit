<?php

namespace App\Http\Controllers;

use App\Models\Organizacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InternalModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_wnfit_internal, 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['q'] ?? '');

        $organizations = Organizacao::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nome_fantasia', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%')))
            ->orderBy('nome_fantasia')
            ->orderBy('id')
            ->paginate(20)
            ->through($this->payload(...));

        return response()->json($organizations);
    }

    public function update(Request $request, int $organizationId): JsonResponse
    {
        abort_unless($request->user()->is_wnfit_internal, 403);
        $data = $request->validate([
            'module' => ['required', Rule::in(array_keys(Organizacao::ADDITIONAL_MODULE_DEFAULTS))],
            'enabled' => ['required', 'boolean'],
        ]);

        $organization = DB::transaction(function () use ($organizationId, $data) {
            $organization = Organizacao::query()->lockForUpdate()->findOrFail($organizationId);
            $organization->modules = array_replace($organization->modules ?? [], [$data['module'] => (bool) $data['enabled']]);
            $organization->save();

            return $organization;
        }, 3);

        return response()->json($this->payload($organization));
    }

    private function payload(Organizacao $organization): array
    {
        return [
            'id' => $organization->id,
            'name' => $organization->nome_fantasia,
            'slug' => $organization->slug,
            'active' => (bool) $organization->ativa,
            'modules' => $organization->moduleAccess(),
        ];
    }
}
