<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();

        return response()->json([
            'users' => $organization->usuarios()
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->payload($user))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $this->ensureManager($request);

        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['administrador', 'professor', 'atendimento'])],
        ], [
            'email.unique' => 'Ja existe um usuario com este e-mail.',
            'password.min' => 'A senha inicial deve ter pelo menos 8 caracteres.',
        ]);

        $user = DB::transaction(function () use ($data, $organization) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $organization->usuarios()->attach($user->id, [
                'papel' => $data['role'],
                'status' => 'ativo',
            ]);

            return $user;
        });

        return response()->json([
            'user' => $this->payload($organization->usuarios()->findOrFail($user->id)),
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $this->ensureManager($request);

        abort_unless($organization->usuarios()->whereKey($user->id)->exists(), 404);

        $currentRole = $organization->usuarios()->findOrFail($user->id)->pivot->papel;
        if ($currentRole === 'proprietario' && array_key_exists('role', $request->all())) {
            return response()->json(['message' => 'O perfil do proprietario nao pode ser alterado.'], 422);
        }

        $data = $request->validate([
            'role' => ['sometimes', Rule::in(['administrador', 'professor', 'atendimento'])],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        if ($request->user()->is($user) && ($data['status'] ?? null) === 'inativo') {
            return response()->json(['message' => 'Voce nao pode inativar o proprio acesso.'], 422);
        }

        $organization->usuarios()->updateExistingPivot($user->id, array_filter([
            'papel' => $data['role'] ?? null,
            'status' => $data['status'] ?? null,
        ]));

        return response()->json(['user' => $this->payload($organization->usuarios()->findOrFail($user->id))]);
    }

    private function ensureManager(Request $request): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $role = $organization->usuarios()->whereKey($request->user()->id)->first()?->pivot?->papel;

        abort_unless(in_array($role, ['proprietario', 'administrador'], true), 403);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => collect(explode(' ', $user->name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''),
            'role' => $user->pivot?->papel,
            'status' => $user->pivot?->status,
        ];
    }
}
