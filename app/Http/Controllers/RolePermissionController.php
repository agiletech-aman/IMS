<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CentreContextService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(CentreContextService $centreContext): View
    {
        $centre = $centreContext->selected();

        // With no specific Centre selected, each Centre may have diverged
        // permissions for the same role+module — there's no single boolean
        // a checkbox could show for both, so the grid renders empty/read-only
        // (via the centre-warning banner) rather than silently picking one.
        $permissions = $centre === null
            ? collect()
            : RolePermission::where('centre', $centre)
                ->get()
                ->keyBy(fn (RolePermission $permission) => $permission->role.':'.$permission->module);

        return view('roles-permissions.index', [
            'roles' => User::ROLES,
            'modules' => RolePermission::MODULES,
            'actions' => RolePermission::ACTIONS,
            'centre' => $centre,
            'permissions' => $permissions,
        ]);
    }

    public function update(
        Request $request,
        PermissionService $permissions,
        AuditLogger $audit,
        CentreContextService $centreContext,
    ): RedirectResponse {
        $centre = $centreContext->requireSelected();
        $submitted = $request->input('permissions', []);

        foreach (User::ROLES as $role) {
            $settingsAccess = isset($submitted[$role]['settings']['view']);
            foreach (RolePermission::MODULES as $module => $configuration) {
                $allowedActions = $configuration['actions'];
                $moduleAccess = isset($submitted[$role][$module]['view'])
                    && (! str_starts_with($module, 'settings_') || $settingsAccess);
                $values = [];
                foreach (RolePermission::ACTIONS as $action) {
                    $values['can_'.$action] = $moduleAccess
                        && in_array($action, $allowedActions, true)
                        && isset($submitted[$role][$module][$action]);
                }

                RolePermission::updateOrCreate(
                    ['role' => $role, 'module' => $module, 'centre' => $centre],
                    $values,
                );
            }
        }

        $permissions->clear();
        $audit->record(
            'UPDATE',
            'Roles & Permissions',
            "Role permissions were updated for all modules ({$centre}).",
            metadata: ['roles' => User::ROLES, 'modules' => array_keys(RolePermission::MODULES), 'centre' => $centre],
        );

        return back()->with('success', 'Role permissions updated successfully.');
    }
}
