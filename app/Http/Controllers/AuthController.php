<?php

namespace App\Http\Controllers;

use App\Models\Organizacao;
use App\Models\Plano;
use App\Models\User;
use App\Rules\BrazilianPhone;
use App\Services\OrganizationUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'csrf' => csrf_token(),
                'user' => null,
                'organization' => null,
            ]);
        }

        return response()->json($this->sessionPayload($user));
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail valido.',
            'password.required' => 'Informe sua senha.',
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas nao conferem.',
            ]);
        }

        if (! $this->activeOrganization($request->user())) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta conta nao possui uma empresa ativa.',
            ]);
        }

        $request->session()->regenerate();

        Auth::guard('student')->logout();

        return response()->json($this->sessionPayload($request->user()));
    }

    public function checkRegistrationEmail(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return response()->json([
            'available' => ! User::query()->where('email', $data['email'])->exists(),
        ])->header('Cache-Control', 'no-store');
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
            'company' => trim((string) $request->input('company')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['required', 'string', 'min:2', 'max:255'],
            'company_type' => ['nullable', 'required_without:onboarding_profile', 'string', 'in:academia,studio,personal'],
            'onboarding_profile' => ['sometimes', 'required', 'array:student_range,service_mode,team,priorities'],
            'onboarding_profile.student_range' => ['required_with:onboarding_profile', 'string', 'in:starting,1_20,21_50,51_100,100_plus'],
            'onboarding_profile.service_mode' => ['required_with:onboarding_profile', 'string', 'in:in_person,online,hybrid'],
            'onboarding_profile.team' => ['required_with:onboarding_profile', 'string', 'in:solo,team'],
            'onboarding_profile.priorities' => ['required_with:onboarding_profile', 'array', 'min:1', 'max:3'],
            'onboarding_profile.priorities.*' => ['required', 'string', 'distinct', 'in:workouts,schedule,finance,progress,events'],
            'phone' => ['nullable', 'string', new BrazilianPhone],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms' => ['accepted'],
        ], [
            'name.min' => 'Informe seu nome com pelo menos dois caracteres.',
            'company.min' => 'Informe um nome com pelo menos dois caracteres.',
            'phone.string' => 'Informe o telefone como uma sequência de números.',
            'name.required' => 'Informe seu nome completo.',
            'email.required' => 'Informe seu e-mail.',
            'email.email' => 'Informe um e-mail valido.',
            'email.unique' => 'Ja existe uma conta com este e-mail.',
            'company.required' => 'Informe o nome da empresa.',
            'company_type.required' => 'Selecione o tipo de negocio.',
            'company_type.in' => 'Selecione um tipo de negocio valido.',
            'password.required' => 'Crie uma senha.',
            'password.min' => 'A senha deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmacao de senha nao confere.',
            'terms.accepted' => 'Voce precisa aceitar os termos para continuar.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $organization = Organizacao::query()->create([
                'nome_fantasia' => $data['company'],
                'slug' => $this->uniqueOrganizationSlug($data['company']),
                'tipo' => $data['company_type'] ?? null,
                'onboarding_profile' => $data['onboarding_profile'] ?? null,
                'email_contato' => $data['email'],
                'telefone_contato' => $data['phone'] ?? null,
            ]);

            app(OrganizationUnitService::class)->defaultFor($organization->id);

            $organization->usuarios()->attach($user->id, [
                'papel' => 'proprietario',
                'telefone' => $data['phone'] ?? null,
                'status' => 'ativo',
            ]);

            Plano::query()->create([
                'organizacao_id' => $organization->id,
                'nome' => 'Plano Mensal',
                'valor_mensal' => 120,
                'ciclo' => 'mensal',
                'ativo' => true,
            ]);

            return $user;
        });

        Auth::login($user);
        Auth::guard('student')->logout();
        $request->session()->regenerate();

        return response()->json($this->sessionPayload($user), 201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'ok' => true,
            'csrf' => csrf_token(),
        ]);
    }

    private function sessionPayload(User $user): array
    {
        $organization = $this->activeOrganization($user);

        return [
            'csrf' => csrf_token(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'organization' => $organization ? [
                'id' => $organization->id,
                'name' => $organization->nome_fantasia,
                'type' => $organization->tipo,
                'email' => $organization->email_contato,
                'phone' => $organization->telefone_contato,
                'onboarding_profile' => $organization->onboarding_profile,
            ] : null,
        ];
    }

    private function activeOrganization(User $user): ?Organizacao
    {
        return $user->organizacoes()
            ->where('organizacoes.ativa', true)
            ->wherePivot('status', 'ativo')
            ->first();
    }

    private function uniqueOrganizationSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organizacao';
        $slug = $base;
        $suffix = 2;

        while (Organizacao::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
