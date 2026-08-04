<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\AuditLogger;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $requestedRole = (string) $request->query('role');
        $selectedRole = in_array($requestedRole, User::ROLES, true) ? $requestedRole : null;
        $query = User::query()
            ->where('login_enabled', (bool) $selectedRole)
            ->when($selectedRole, fn ($builder) => $builder->where('role', $selectedRole));
        $statsQuery = User::query()
            ->where('login_enabled', (bool) $selectedRole)
            ->when($selectedRole, fn ($builder) => $builder->where('role', $selectedRole));

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('unique_id', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
            });
        }

        return view('users.index', [
            'users' => $query->latest()->paginate(10)->withQueryString(),
            'roles' => User::ROLES,
            'selectedRole' => $selectedRole,
            'availableAssets' => Asset::with('type:id,name')
                ->whereNull('assigned_to')
                ->orderBy('name')
                ->orderBy('asset_tag')
                ->get(['id', 'asset_tag', 'name', 'asset_type_id', 'status']),
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('status', 'Active')->count(),
                'inactive' => (clone $statsQuery)->where('status', 'Inactive')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $data['unique_id'] = UniqueCodeGenerator::generate('users', 'USR', 'users', 'unique_id');
        $data['login_enabled'] = $request->boolean('login_enabled');
        $data['role'] = $data['login_enabled'] ? $data['role'] : 'Viewer';
        $data['password'] = $data['login_enabled'] ? $data['password'] : Str::password(32);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);
        $user = User::create($data);
        $this->notifications->send(
            'user_created',
            $user->login_enabled ? 'New access account created' : 'New user created',
            $user->login_enabled
                ? "{$user->name} ({$user->unique_id}) was given {$user->role} dashboard access."
                : "{$user->name} ({$user->unique_id}) was added as a non-login asset user.",
            'info',
            'Users',
            ['user_id' => $user->id],
        );

        return back()->with(
            'success',
            $user->login_enabled
                ? "{$user->role} dashboard access account created successfully."
                : 'User created successfully.',
        );
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules($user->id));
        $data['login_enabled'] = $request->boolean('login_enabled');
        $data['role'] = $data['login_enabled'] ? $data['role'] : 'Viewer';
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->hasFile('image')) {
            if ($user->image_path) {
                Storage::disk('public')->delete($user->image_path);
            }

            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);
        $user->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    public function assignAsset(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'asset_type_id' => ['required', 'exists:asset_types,id'],
            'asset_id' => [
                'required',
                Rule::exists('assets', 'id')->where(fn ($query) => $query
                    ->whereNull('assigned_to')
                    ->where('asset_type_id', $request->integer('asset_type_id'))),
            ],
        ], [
            'asset_id.exists' => 'This asset is no longer available for assignment.',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        $asset->update([
            'assigned_to' => $user->name,
            'status' => $asset->status === 'In Stock' ? 'Active' : $asset->status,
        ]);
        $this->audit->record(
            'ASSIGN',
            'Assets',
            "{$asset->asset_tag} — {$asset->name} was assigned to {$user->name}.",
            $asset,
            ['assigned_to' => null],
            ['assigned_to' => $user->name, 'user_id' => $user->id],
        );
        $this->notifications->send(
            'asset_assigned',
            'Asset assigned',
            "{$asset->asset_tag} — {$asset->name} was assigned to {$user->name}.",
            'success',
            'Assets',
            ['asset_id' => $asset->id, 'user_id' => $user->id],
        );

        return back()->with('success', "{$asset->asset_tag} assigned to {$user->name} successfully.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $imagePath = $user->image_path;
        $userName = $user->name;
        $userCode = $user->unique_id;
        $user->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }
        $this->notifications->send(
            'user_deleted',
            'User deleted',
            "{$userName} ({$userCode}) was removed from the user directory.",
            'warning',
            'Users',
        );

        return back()->with('success', 'User deleted successfully.');
    }

    private function rules(?int $userId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'contact' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'login_enabled' => ['nullable', 'boolean'],
            'role' => ['required_if:login_enabled,1', 'nullable', Rule::in(User::ROLES)],
            'password' => [
                $userId === null ? 'required_if:login_enabled,1' : 'nullable',
                'nullable',
                'string',
                'min:8',
                'max:255',
            ],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}
