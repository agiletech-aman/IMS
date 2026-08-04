<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->session()->get('static_auth_user.role') === 'Administrator',
            403,
            'Only an administrator can manage administrator accounts.',
        );

        return $next($request);
    }
}
