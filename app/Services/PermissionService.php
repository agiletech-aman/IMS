<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Schema;

class PermissionService
{
    private array $cache = [];

    public function __construct(private readonly CentreContextService $centreContext) {}

    public function isAdministrator(): bool
    {
        return session('static_auth_user.role') === 'Administrator';
    }

    public function allows(string $module, string $action = 'view'): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $role = session('static_auth_user.role');
        if (! $role || ! Schema::hasTable('role_permissions')) {
            return false;
        }

        // A logged-in non-Administrator is always pinned to their own
        // centre by CentreContextService::selected(), so this is never
        // null for the accounts whose permissions are checked here.
        $centre = $this->centreContext->selected();

        $key = "{$role}:{$module}:{$centre}";
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = RolePermission::where('role', $role)
                ->where('module', $module)
                ->where('centre', $centre)
                ->first();
        }

        return (bool) $this->cache[$key]?->getAttribute('can_'.$action);
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
