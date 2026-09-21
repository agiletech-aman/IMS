<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetAssignmentHistory;
use App\Models\Faculty;
use App\Models\User;
use App\Services\CentreContextService;
use App\Services\NotificationService;
use App\Services\AuditLogger;
use App\Support\UniqueCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
        private readonly CentreContextService $centreContext,
    ) {}

    public function index(Request $request): View
    {
        $query = Faculty::query()->with(['department', 'assignedAssets:id,name,asset_tag,assigned_to,asset_type_id']);
        $this->centreContext->apply($query);

        $statsQuery = Faculty::query();
        $this->centreContext->apply($statsQuery);

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

        $status = (string) $request->query('status');
        if (in_array($status, ['Active', 'Inactive'], true)) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 10);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }

        $users = $query->latest()->paginate($perPage)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => User::ROLES,
            'departments' => \App\Models\Department::query()
                ->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedRole' => null,
            'availableAssets' => Asset::with('type:id,name')
                ->where(fn($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', ''))
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
        $data['unique_id'] = UniqueCodeGenerator::generate('faculties', 'USR', 'faculties', 'unique_id');
        $data['centre'] = $this->centreContext->requireSelected();
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);
        $user = Faculty::create($data);
        $this->notifications->send(
            'user_created',
            'New user created',
            "{$user->name} ({$user->unique_id}) was added as a non-login asset user.",
            'info',
            'Users',
            ['user_id' => $user->id],
        );

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, int $user): RedirectResponse
    {
        $account = Faculty::findOrFail($user);

        $data = $request->validate($this->rules($account->id));
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($request->hasFile('image')) {
            if ($account->image_path) {
                Storage::disk('public')->delete($account->image_path);
            }

            $data['image_path'] = $request->file('image')->store('users', 'public');
        }

        unset($data['image']);
        $account->update($data);

        return back()->with('success', 'User updated successfully.');
    }

    public function assignAsset(Request $request, Faculty $user): RedirectResponse
    {
        $data = $request->validate([
            'asset_ids' => ['nullable', 'array'],
            'asset_ids.*' => [
                Rule::exists('assets', 'id')->where(
                    fn($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '')
                ),
            ],
            'keep_asset_ids' => ['nullable', 'array'],
            'keep_asset_ids.*' => ['integer'],
        ], [
            'asset_ids.*.exists' => 'One or more selected assets are no longer available for assignment.',
        ]);

        $actor = session('static_auth_user.name')
            ?? session('static_auth_user.email')
            ?? 'System';

        $currentlyAssignedIds = $user->assignedAssets()->pluck('id');
        $keepIds = collect($data['keep_asset_ids'] ?? [])->map(fn ($id) => (int) $id);
        $toUnassign = Asset::whereIn('id', $currentlyAssignedIds->diff($keepIds))->get();

        foreach ($toUnassign as $asset) {
            $asset->update([
                'assigned_to' => null,
                'status' => $asset->status === 'Active' ? 'In Stock' : $asset->status,
            ]);

            AssetAssignmentHistory::where('asset_id', $asset->id)
                ->where('faculty_id', $user->id)
                ->whereNull('unassigned_at')
                ->latest('assigned_at')
                ->first()
                ?->update(['unassigned_at' => now(), 'unassigned_by' => $actor]);

            $this->audit->record(
                'UNASSIGN',
                'Assets',
                "{$asset->asset_tag} — {$asset->name} was unassigned from {$user->name}.",
                $asset,
                ['assigned_to' => $user->name, 'user_id' => $user->id],
                ['assigned_to' => null],
            );

            $this->notifications->send(
                'asset_unassigned',
                'Asset unassigned',
                "{$asset->asset_tag} — {$asset->name} was unassigned from {$user->name}.",
                'info',
                'Assets',
                ['asset_id' => $asset->id, 'user_id' => $user->id],
            );
        }

        $assets = Asset::whereIn('id', $data['asset_ids'] ?? [])
            ->where(fn($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', ''))
            ->get();

        foreach ($assets as $asset) {
            $asset->update([
                'assigned_to' => $user->name,
                'status' => $asset->status === 'In Stock' ? 'Active' : $asset->status,
            ]);

            AssetAssignmentHistory::create([
                'faculty_id' => $user->id,
                'asset_id' => $asset->id,
                'assigned_at' => now(),
                'assigned_by' => $actor,
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
        }

        if ($assets->isEmpty() && $toUnassign->isEmpty()) {
            return back()->with('success', 'No assignment changes were made.');
        }

        $messageParts = [];
        if ($assets->isNotEmpty()) {
            $messageParts[] = $assets->count() === 1
                ? "{$assets->first()->asset_tag} assigned"
                : "{$assets->count()} assets assigned";
        }
        if ($toUnassign->isNotEmpty()) {
            $messageParts[] = $toUnassign->count() === 1
                ? "{$toUnassign->first()->asset_tag} unassigned"
                : "{$toUnassign->count()} assets unassigned";
        }

        return back()->with('success', implode(' and ', $messageParts).' for '.$user->name.'.');
    }

    public function assetHistory(Request $request, Faculty $user): View
    {
        $historyQuery = AssetAssignmentHistory::query()
            ->with(['asset.type', 'asset.department'])
            ->where('faculty_id', $user->id);

        $currentAssignments = (clone $historyQuery)->whereNull('unassigned_at')->orderByDesc('assigned_at')->get();
        $totalAssignments = (clone $historyQuery)->count();

        $filteredQuery = clone $historyQuery;

        if ($search = trim((string) $request->query('search'))) {
            $filteredQuery->where(function ($builder) use ($search): void {
                $builder
                    ->where('assigned_by', 'like', "%{$search}%")
                    ->orWhere('unassigned_by', 'like', "%{$search}%")
                    ->orWhereHas('asset', function ($assetQuery) use ($search): void {
                        $assetQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('asset_tag', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhereHas('type', fn($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('department', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $history = $filteredQuery->orderByDesc('assigned_at')->paginate(10)->withQueryString();

        return view('users.asset-history', compact(
            'user',
            'history',
            'currentAssignments',
            'totalAssignments'
        ));
    }

    public function exportAssetHistory(Faculty $user)
    {
        $history = AssetAssignmentHistory::query()
            ->with(['asset.type', 'asset.department'])
            ->where('faculty_id', $user->id)
            ->orderBy('assigned_at')
            ->get();

        $filename = ($user->unique_id ?: 'user') . '-asset-history-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($user, $history) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Faculty ID', 'Faculty Name', 'Email', 'Asset Tag', 'Asset Name',
                'Asset Type', 'Serial Number', 'Department', 'Assigned At',
                'Unassigned At', 'Assigned By', 'Unassigned By', 'Remarks',
            ]);

            foreach ($history as $item) {
                fputcsv($handle, [
                    $user->unique_id,
                    $user->name,
                    $user->email,
                    $item->asset?->asset_tag,
                    $item->asset?->name,
                    $item->asset?->type?->name,
                    $item->asset?->serial_number,
                    $item->asset?->department?->name,
                    $item->assigned_at?->format('d M Y h:i A'),
                    $item->unassigned_at?->format('d M Y h:i A') ?? 'Current',
                    $item->assigned_by,
                    $item->unassigned_by,
                    $item->remarks,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Faculty $user): RedirectResponse
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
        $centre = $this->centreContext->selected();

        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z ]+$/',
                Rule::unique('faculties', 'name')
                    ->where(fn ($query) => $centre ? $query->where('centre', $centre) : $query)
                    ->ignore($userId),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('faculties', 'email')->ignore($userId)],
            'contact' => ['nullable', 'digits:10'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'fb_type' => ['nullable', 'string', 'max:30'],
            'room_number' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }
}