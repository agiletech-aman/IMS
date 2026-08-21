<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PermissionService $permissions,
    ) {}

    public function showLogin(Request $request): View|RedirectResponse
    {
        if ($request->session()->get('static_auth_user')) {
            $route = $this->firstAccessibleRoute();
            if ($route) {
                return redirect()->route($route);
            }

            $request->session()->forget('static_auth_user');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = Admin::where('email', $credentials['email'])
            ->where('status', 'Active')
            ->first();
        if ($admin && ! Hash::check($credentials['password'], $admin->password)) {
            $admin = null;
        }

        $account = $admin
            ? $admin
            : User::where('email', $credentials['email'])
                ->where('login_enabled', true)
                ->where('status', 'Active')
                ->first();

        if (! $account || (! $admin && ! Hash::check($credentials['password'], $account->password))) {
            if (! Admin::where('email', $credentials['email'])->exists()) {
                $this->audit->record(
                    'LOGIN FAILED',
                    'Authentication',
                    "Invalid login attempt for {$credentials['email']}.",
                    result: 'Blocked',
                    metadata: ['attempted_email' => $credentials['email']],
                );
            }

            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $authenticatedUser = $admin
            ? [
                'admin_id' => $admin->id,
                'user_id' => null,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'Administrator',
                'initials' => $this->initials($admin->name),
                'image_path' => $admin->image_path,
                'centre' => null,
            ]
            : [
                'admin_id' => null,
                'user_id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'role' => $account->role,
                'initials' => $this->initials($account->name),
                'image_path' => $account->image_path,
                'centre' => $account->centre,
            ];

        $request->session()->put('static_auth_user', $authenticatedUser);
        if ($admin) {
            $request->session()->forget('selected_centre');
        }
        if ($admin) {
            $admin->update(['last_login_at' => now()]);
        }
        $this->audit->record(
            'LOGIN',
            'Authentication',
            "{$authenticatedUser['name']} signed in as {$authenticatedUser['role']}.",
            $account,
        );

        $route = $this->firstAccessibleRoute();
        if (! $route) {
            $request->session()->forget('static_auth_user');

            return redirect()->route('login')->withErrors([
                'email' => 'Your account does not have access to any module. Contact an administrator.',
            ]);
        }

        return redirect()->route($route);
    }

    public function logout(Request $request): RedirectResponse
    {
        $actorName = $request->session()->get('static_auth_user.name', 'User');
        $this->audit->record('LOGOUT', 'Authentication', "{$actorName} signed out.");
        $request->session()->forget('static_auth_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->join('');
    }

    private function firstAccessibleRoute(): ?string
    {
        foreach ([
            'dashboard' => 'dashboard',
            'assets' => 'assets.index',
            'departments' => 'asset-management.departments.index',
            'sub_departments' => 'asset-management.sub-departments.index',
            'categories' => 'asset-management.categories.index',
            'types' => 'asset-management.types.index',
            'brands' => 'asset-management.brands.index',
            'faculty' => 'users.index',
            'access_accounts' => 'access-accounts.index',
            'vendors' => 'vendors.index',
            'notifications' => 'notifications.index',
            'reports' => 'reports.index',
            'audit_logs' => 'audit-logs.index',
            'backup' => 'backup.index',
            'settings' => 'settings.index',
            'roles_permissions' => 'roles-permissions.index',
        ] as $module => $route) {
            if ($this->permissions->allows($module)) {
                return $route;
            }
        }

        return null;
    }
}
