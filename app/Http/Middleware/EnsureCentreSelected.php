<?php

namespace App\Http\Middleware;

use App\Services\CentreContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentreSelected
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->session()->get('static_auth_user', []);
        $isAdministrator = ($user['role'] ?? null) === 'Administrator';

        if ($isAdministrator) {
            $centre = app(CentreContextService::class)->selected();
            $request->attributes->set('effective_centre', $centre);
        }

        return $next($request);
    }
}