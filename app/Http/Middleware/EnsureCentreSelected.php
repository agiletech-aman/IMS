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

        if ($isAdministrator
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->routeIs('logout')) {
            try {
                app(CentreContextService::class)->requireSelected();
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        return $next($request);
    }
}