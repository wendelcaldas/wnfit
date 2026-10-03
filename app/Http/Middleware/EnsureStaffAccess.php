<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStaffAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->organizacoes()->where('organizacoes.ativa', true)->wherePivot('status', 'ativo')->exists(), 403, 'Acesso exclusivo da equipe.');

        return $next($request);
    }
}
