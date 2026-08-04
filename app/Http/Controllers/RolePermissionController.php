<?php

namespace App\Http\Controllers;

use App\Models\RolePermission;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RolePermissionController extends Controller
{
    public function index(): View
    {
        return view('roles-permissions.index', [
            'roles' => User::ROLES,
            'modules' => RolePermission::MODULES,
            'actions' => RolePermission::ACTIONS,
            'permissions' => RolePermission::all()->keyBy(fn (RolePermission $permission) => $permission->role.':'.$permission->module),
        ]);
    }

    public function update(
        Request $request,
        PermissionService $permissions,
        AuditLogger $audit,
    ): RedirectResponse {
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
                    ['role' => $role, 'module' => $module],
                    $values,
                );
            }
        }

        $permissions->clear();
        $audit->record(
            'UPDATE',
            'Roles & Permissions',
            'Role permissions were updated for all modules.',
            metadata: ['roles' => User::ROLES, 'modules' => array_keys(RolePermission::MODULES)],
        );

        return back()->with('success', 'Role permissions updated successfully.');
    }
}
