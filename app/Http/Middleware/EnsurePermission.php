<?php

namespace App\Http\Middleware;

use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $allowed = $this->permissions->allows($module, 'view')
            && ($action === 'view' || $this->permissions->allows($module, $action));

        abort_unless(
            $allowed,
            403,
            "You do not have {$action} permission for this module.",
        );

        return $next($request);
    }
}
