<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureStudentAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'ready')
    {
        $account = Auth::guard('student')->user();
        abort_unless($account, 401, 'Entre para continuar.');
        if ($request->session()->get('student_credential_version') !== $account->credential_version) {
            Auth::guard('student')->logout();
            abort(401, 'Sua senha foi redefinida. Entre novamente.');
        }
        abort_unless($account->aluno?->organizacao?->ativa, 403, 'Sua academia está indisponível.');
        if ($mode !== 'password') {
            abort_if($account->must_change_password, 403, 'Defina sua nova senha para continuar.');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
