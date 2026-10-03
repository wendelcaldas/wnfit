<?php

namespace App\Http\Controllers;

use App\Models\StudentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentPortalAuthController extends Controller
{
    public function me(Request $request)
    {
        $account = Auth::guard('student')->user();
        if ($account && ($request->session()->get('student_credential_version') !== $account->credential_version || ! $account->aluno?->organizacao?->ativa)) {
            Auth::guard('student')->logout();
            $account = null;
        }

        return response()->json($this->payload($account));
    }

    public function login(Request $request)
    {
        $request->merge(['username' => Str::lower(trim((string) $request->input('username')))]);
        $data = $request->validate(['username' => ['required', 'string', 'max:60'], 'password' => ['required', 'string', 'max:255']]);
        $account = StudentAccount::where('username', $data['username'])->first();
        if (! $account || ! Hash::check($data['password'], $account->password) || ! $account->aluno?->organizacao?->ativa) {
            // Avoid distinguishing an unknown username from an invalid password.
            if (! $account) {
                Hash::check($data['password'], '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
            }
            throw ValidationException::withMessages(['username' => 'Usuário ou senha não conferem.']);
        }
        Auth::guard('web')->logout();
        Auth::guard('student')->login($account);
        $request->session()->regenerate();
        $request->session()->put('student_credential_version', $account->credential_version);
        $account->update(['last_login_at' => now()]);

        return response()->json($this->payload($account));
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed', 'different:current_password']]);
        $account = DB::transaction(function () use ($data, $request) {
            $account = StudentAccount::whereKey(Auth::guard('student')->id())->lockForUpdate()->firstOrFail();
            abort_unless($request->session()->get('student_credential_version') === $account->credential_version, 401, 'Sua senha foi redefinida. Entre novamente.');
            if (! Hash::check($data['current_password'], $account->password)) {
                throw ValidationException::withMessages(['current_password' => 'A senha atual não confere.']);
            }
            $account->update(['password' => $data['password'], 'must_change_password' => false, 'credential_version' => $account->credential_version + 1]);

            return $account;
        });
        Auth::guard('student')->setUser($account);
        $request->session()->regenerate();
        $request->session()->put('student_credential_version', $account->credential_version);

        return response()->json($this->payload($account));
    }

    public function logout(Request $request)
    {
        Auth::guard('student')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['csrf' => csrf_token(), 'student' => null]);
    }

    private function payload(?StudentAccount $account): array
    {
        return ['csrf' => csrf_token(), 'student' => $account ? [
            'id' => $account->aluno_id, 'name' => $account->aluno->nome, 'username' => $account->username,
            'mustChangePassword' => $account->must_change_password, 'organization' => $account->aluno->organizacao->nome_fantasia,
        ] : null];
    }
}
