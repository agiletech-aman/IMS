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

        if ($isAdministrator && $this->requiresCentre($request)) {
            try {
                app(CentreContextService::class)->requireSelected();
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                return back()->with('error', $exception->getMessage());
            }
        }

        return $next($request);
    }

    /**
     * A specific centre is only required for actions that create or update
     * a centre-scoped record (create/update/import). Delete, view, export,
     * assign, and other actions read/act on existing data and should work
     * against the compiled (all centres) view when no centre is selected.
     */
    private function requiresCentre(Request $request): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        // Backups snapshot the database itself (whole or filtered by the
        // selected centre) rather than creating a single centre-owned
        // record, so the module manages its own compiled/centre behaviour.
        if ($request->routeIs('backup.*')) {
            return false;
        }

        $method = $request->route()?->getActionMethod();

        if (! $method) {
            return false;
        }

        return in_array($method, ['store', 'update'], true)
            || str_contains(strtolower($method), 'import');
    }
}