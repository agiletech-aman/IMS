<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    private const RESULTS_PER_GROUP = 5;

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

        if ($this->permissions->allows('users')) {
            foreach ($this->users($query) as $user) {
                $parameters = ['search' => $user->unique_id];
                $route = 'users.index';

                if ($user->login_enabled) {
                    $parameters['role'] = $user->role;
                    $route = 'access-accounts.index';
                }

                $results[] = [
                    'type' => 'Users',
                    'icon' => 'user',
                    'title' => $user->name,
                    'description' => collect([$user->unique_id, $user->email])->filter()->join(' · '),
                    'url' => route($route, $parameters),
                ];
            }
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

    private function users(string $query)
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
            ->get(['id', 'unique_id', 'name', 'email', 'role', 'login_enabled']);
    }
}
