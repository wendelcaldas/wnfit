<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\BrazilianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();

        return response()->json([
            'canManage' => $organization->ativa && $organization->pivot->status === 'ativo'
                && in_array($organization->pivot->papel, ['proprietario', 'administrador'], true),
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
            'phone' => ['nullable', 'string', 'max:30', new BrazilianPhone],
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
                'telefone' => $this->phone($data['phone'] ?? null),
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30', new BrazilianPhone],
            'role' => ['sometimes', Rule::in(['administrador', 'professor', 'atendimento'])],
            'status' => ['sometimes', Rule::in(['ativo', 'inativo'])],
        ]);

        if ($request->user()->is($user) && ($data['status'] ?? null) === 'inativo') {
            return response()->json(['message' => 'Voce nao pode inativar o proprio acesso.'], 422);
        }

        $identity = array_intersect_key($data, array_flip(['name', 'email']));
        $changedIdentity = array_filter($identity, fn ($value, $key) => $value !== $user->$key, ARRAY_FILTER_USE_BOTH);
        if ($changedIdentity && ! $request->user()->is($user) && $user->organizacoes()->count() > 1) {
            throw ValidationException::withMessages(['name' => 'Esta conta é compartilhada entre ambientes. O próprio usuário deve alterar nome e e-mail.']);
        }
        DB::transaction(function () use ($organization, $user, $data, $identity, $currentRole) {
            $user->update($identity);
            $pivot = array_filter([
                'papel' => $data['role'] ?? null,
                'status' => $data['status'] ?? null,
            ]);
            if (array_key_exists('phone', $data)) {
                $pivot['telefone'] = $this->phone($data['phone']);
                if ($currentRole === 'proprietario') {
                    $organization->update(['telefone_contato' => $pivot['telefone']]);
                }
            }
            $organization->usuarios()->updateExistingPivot($user->id, $pivot);
        });

        return response()->json(['user' => $this->payload($organization->usuarios()->findOrFail($user->id))]);
    }

    private function ensureManager(Request $request): void
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $role = $organization->usuarios()->whereKey($request->user()->id)->first()?->pivot?->papel;

        abort_unless($organization->ativa && $organization->pivot->status === 'ativo'
            && in_array($role, ['proprietario', 'administrador'], true), 403);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $this->ensureManager($request);
        $member = $organization->usuarios()->findOrFail($user->id);
        abort_if($request->user()->is($user) || $member->pivot->papel === 'proprietario', 422, 'O proprietário e o próprio acesso não podem ser excluídos.');
        $organization->usuarios()->detach($user->id);

        return response()->json(['ok' => true]);
    }

    private function phone(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', $value ?? '');
        if ($digits !== '' && ! preg_match('/\A(?:55)?[1-9][0-9]{9,10}\z/', $digits)) {
            throw ValidationException::withMessages(['phone' => 'Informe um telefone válido com DDD.']);
        }

        return $digits ?: null;
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
            'phone' => $user->pivot?->telefone,
        ];
    }
}
