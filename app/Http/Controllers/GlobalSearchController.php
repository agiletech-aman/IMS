<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Faculty;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const RESULTS_PER_GROUP = 5;

    private const ACCESS_ACCOUNT_MODULES = [
        'Asset Manager' => 'access_accounts_asset_manager',
        'Sub admin' => 'access_accounts_sub_admin',
        'Auditor' => 'access_accounts_auditor',
        'Viewer' => 'access_accounts_viewer',
    ];

    public function __construct(private readonly PermissionService $permissions) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($data['query'] ?? ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        if ($this->permissions->allows('assets')) {
            foreach ($this->assets($query) as $asset) {
                $results[] = [
                    'type' => 'Assets',
                    'icon' => 'asset',
                    'title' => "{$asset->asset_tag} · {$asset->name}",
'description' => collect([$asset->fr_number, $asset->status])->filter()->join(' · '),
                    'url' => route('assets.show', $asset),
                ];
            }
        }

        if ($this->permissions->allows('faculty')) {
            foreach ($this->faculty($query) as $person) {
                $results[] = [
                    'type' => 'Users',
                    'icon' => 'user',
                    'title' => $person->name,
                    'description' => collect([$person->unique_id, $person->email])->filter()->join(' · '),
                    'url' => route('users.index', ['search' => $person->unique_id]),
                ];
            }
        }

        foreach ($this->accessAccounts($query) as $account) {
            $module = self::ACCESS_ACCOUNT_MODULES[$account->role] ?? null;

            if (! $module || ! $this->permissions->allows($module)) {
                continue;
            }

            $results[] = [
                'type' => 'Access Accounts',
                'icon' => 'user',
                'title' => $account->name,
                'description' => collect([$account->unique_id, $account->email])->filter()->join(' · '),
                'url' => route('access-accounts.index', ['role' => $account->role, 'search' => $account->unique_id]),
            ];
        }

        return response()->json(['results' => $results]);
    }

    private function assets(string $query)
    {
        return Asset::query()
            ->where(function ($builder) use ($query): void {
$builder->where('asset_tag', 'like', "%{$query}%")
                    ->orWhere('name', 'like', "%{$query}%")
                    ->orWhere('serial_number', 'like', "%{$query}%")
                    ->orWhere('fr_number', 'like', "%{$query}%");
            })
            ->orderBy('asset_tag')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'asset_tag', 'name', 'fr_number', 'status']);
    }

    private function faculty(string $query)
    {
        return Faculty::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('unique_id', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'unique_id', 'name', 'email', 'contact']);
    }

    private function accessAccounts(string $query)
    {
        return User::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('unique_id', 'like', "%{$query}%")
                    ->orWhere('contact', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(self::RESULTS_PER_GROUP)
            ->get(['id', 'unique_id', 'name', 'email', 'role']);
    }
}
