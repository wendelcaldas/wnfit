<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOrganizationModule
{
    public function handle(Request $request, Closure $next, string ...$modules)
    {
        $organization = $request->user()->activeOrganization();
        abort_unless($organization && collect($modules)->contains($organization->hasModule(...)), 403, 'Módulo indisponível neste ambiente.');

        return $next($request);
    }
}
