<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CentreContextService;
use App\Services\NotificationService;
use App\Services\PermissionService;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccessAccountController extends Controller
{
    private const ROLE_MODULES = [
        'Asset Manager' => 'access_accounts_asset_manager',
        'Sub admin' => 'access_accounts_sub_admin',
        'Auditor' => 'access_accounts_auditor',
        'Viewer' => 'access_accounts_viewer',
    ];

    public function __construct(
        private readonly CentreContextService $centreContext,
        private readonly NotificationService $notifications,
        private readonly PermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        $selectedRole = $this->selectedRole($request);
        $this->authorizeRole($selectedRole);

        $query = User::query()
            ->where('role', $selectedRole)
            ->where('login_enabled', true);

        $this->centreContext->apply($query);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('unique_id', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (in_array($status = (string) $request->query('status'), ['Active', 'Inactive'], true)) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $statsQuery = User::query()
            ->where('role', $selectedRole)
            ->where('login_enabled', true);

        $this->centreContext->apply($statsQuery);

        return view('users.index', [
            'users' => $query->latest()->paginate($perPage)->withQueryString(),
            'roles' => User::ROLES,
            'departments' => collect(),
            'selectedRole' => $selectedRole,
            'availableAssets' => collect(),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('status', 'Active')->count(),
                'inactive' => (clone $statsQuery)->where('status', 'Inactive')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $selectedRole = $this->selectedRole($request);
        $this->authorizeRole($selectedRole, 'create');

        $data = $request->validate($this->rules());

        $data['unique_id'] = UniqueCodeGenerator::generate('people', 'ID', ['users', 'faculties'], 'unique_id');
        $data['role'] = $selectedRole;
        $data['login_enabled'] = true;
        $data['centre'] = $this->centreContext->requireSelected();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);

        $account = User::create($data);

        $this->notifications->send(
            'access_account_created',
            'Access account created',
            "{$account->name} ({$account->unique_id}) was given {$selectedRole} dashboard access.",
            'info',
            'Access Accounts',
            ['user_id' => $account->id],
        );

        return redirect()
            ->route('access-accounts.index', ['role' => $selectedRole])
            ->with('success', "{$selectedRole} access account created successfully.");
    }

    public function update(Request $request, User $accessAccount): RedirectResponse
    {
        $selectedRole = $this->selectedRole($request);
        $this->authorizeRole($selectedRole, 'update');
        $this->ensureCurrentCentre($accessAccount);

        abort_unless(
            $accessAccount->login_enabled && $accessAccount->role === $selectedRole,
            404,
        );

        $data = $request->validate($this->rules($accessAccount));

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->hasFile('image')) {
            if ($accessAccount->image_path) {
                Storage::disk('public')->delete($accessAccount->image_path);
            }

            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);
        $accessAccount->update($data);

        return redirect()
            ->route('access-accounts.index', ['role' => $selectedRole])
            ->with('success', 'Access account updated successfully.');
    }

    public function destroy(Request $request, User $accessAccount): RedirectResponse
    {
        $selectedRole = $this->selectedRole($request);
        $this->authorizeRole($selectedRole, 'delete');
        $this->ensureCurrentCentre($accessAccount);

        abort_unless(
            $accessAccount->login_enabled && $accessAccount->role === $selectedRole,
            404,
        );

        $imagePath = $accessAccount->image_path;
        $accountName = $accessAccount->name;
        $accessAccount->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        $this->notifications->send(
            'access_account_deleted',
            'Access account deleted',
            "{$accountName}'s {$selectedRole} dashboard access was removed.",
            'warning',
            'Access Accounts',
        );

        return redirect()
            ->route('access-accounts.index', ['role' => $selectedRole])
            ->with('success', 'Access account deleted successfully.');
    }

    private function selectedRole(Request $request): string
    {
        $role = (string) $request->query('role', User::ROLES[0]);

        abort_unless(in_array($role, User::ROLES, true), 404);

        return $role;
    }

    private function authorizeRole(string $role, string $action = 'view'): void
    {
        $module = self::ROLE_MODULES[$role] ?? null;

        abort_unless(
            $module !== null
                && $this->permissions->allows($module, 'view')
                && ($action === 'view' || $this->permissions->allows($module, $action)),
            403,
            "You do not have {$action} permission for this module.",
        );
    }

    private function ensureCurrentCentre(User $accessAccount): void
    {
        $centre = $this->centreContext->selected();

        abort_if($centre !== null && $accessAccount->centre !== $centre, 404);
    }

    private function rules(?User $accessAccount = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($accessAccount?->id),
            ],
            'contact' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'password' => [
                $accessAccount ? 'nullable' : 'required',
                'string',
                'min:8',
                'max:255',
            ],
        ];
    }
}
