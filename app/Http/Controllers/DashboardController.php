<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\SystemNotification;
use App\Models\User;
use App\Models\Vendor;
use App\Services\PermissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, PermissionService $permissions): View
    {
        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        [$from, $to, $period] = $this->dateRange($filters);
        $today = today();
        $nextThirtyDays = today()->addDays(30);
        $visibility = [
            'assets' => $permissions->allows('assets'),
            'users' => $permissions->allows('users'),
            'vendors' => $permissions->allows('vendors'),
            'notifications' => $permissions->allows('notifications'),
            'reports' => $permissions->allows('reports'),
            'audit' => $permissions->allows('audit_logs'),
        ];

$categoryDistribution = $visibility['assets'] ? Asset::query()
            ->leftJoin('asset_types', 'assets.asset_type_id', '=', 'asset_types.id')
            ->selectRaw("COALESCE(asset_types.name, 'Uncategorized') as label, COUNT(assets.id) as total")
            ->groupBy('asset_types.name')
            ->orderByDesc('total')
            ->get() : collect();

        $statusLabels = ['Active', 'In Stock', 'Under Maintenance', 'Retired'];
        $statusCounts = $visibility['assets'] ? Asset::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status') : collect();

        $activityBreakdown = $visibility['audit'] ? AuditLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(8)
            ->get() : collect();

        $coverageForecast = [
            'labels' => ['Expired', 'Due in 30 days', '31–60 days', '61–90 days'],
            'warranty' => $visibility['reports'] && $visibility['assets'] ? $this->coverageBuckets('warranty_expiry') : [0, 0, 0, 0],
            'amc' => $visibility['reports'] && $visibility['assets'] ? $this->coverageBuckets('amc_expiry') : [0, 0, 0, 0],
        ];

        $stats = [];
        if ($visibility['assets']) {
            $stats = [
                ['icon' => 'fa-cubes', 'label' => 'Total Assets', 'value' => Asset::count(), 'class' => ''],
                ['icon' => 'fa-circle-check', 'label' => 'Active Assets', 'value' => Asset::where('status', 'Active')->count(), 'class' => 'success'],
                ['icon' => 'fa-box-open', 'label' => 'In Stock', 'value' => Asset::where('status', 'In Stock')->count(), 'class' => 'info'],
                ['icon' => 'fa-screwdriver-wrench', 'label' => 'Maintenance', 'value' => Asset::where('status', 'Under Maintenance')->count(), 'class' => 'warning'],
                ['icon' => 'fa-user-check', 'label' => 'Assigned Assets', 'value' => Asset::whereNotNull('assigned_to')->where('assigned_to', '!=', '')->count(), 'class' => 'success'],
                ['icon' => 'fa-link-slash', 'label' => 'Unassigned Assets', 'value' => Asset::whereNull('assigned_to')->orWhere('assigned_to', '')->count(), 'class' => 'warning'],
            ];
        }
        if ($visibility['users']) {
            $stats[] = ['icon' => 'fa-users', 'label' => 'Users', 'value' => User::where('login_enabled', false)->count(), 'class' => ''];
            $stats[] = ['icon' => 'fa-key', 'label' => 'Access Accounts', 'value' => User::where('login_enabled', true)->count(), 'class' => 'info'];
        }
        if ($visibility['vendors']) {
            $stats[] = ['icon' => 'fa-handshake', 'label' => 'Active Vendors', 'value' => Vendor::where('status', 'Active')->count(), 'class' => 'success'];
        }
        if ($visibility['notifications']) {
            $stats[] = ['icon' => 'fa-bell', 'label' => 'Unread Alerts', 'value' => SystemNotification::where('in_app_visible', true)->whereNull('read_at')->count(), 'class' => 'danger'];
        }
        if ($visibility['reports'] && $visibility['assets']) {
            $stats[] = ['icon' => 'fa-shield-halved', 'label' => 'AMC Due (30d)', 'value' => Asset::whereBetween('amc_expiry', [$today, $nextThirtyDays])->count(), 'class' => 'warning'];
            $stats[] = ['icon' => 'fa-certificate', 'label' => 'Warranty Due (30d)', 'value' => Asset::whereBetween('warranty_expiry', [$today, $nextThirtyDays])->count(), 'class' => 'danger'];
        }

        return view('dashboard.index', [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
            'assetTotal' => $visibility['assets'] ? Asset::count() : 0,
            'visibility' => $visibility,
            'categoryChart' => [
                'labels' => $categoryDistribution->pluck('label'),
                'values' => $categoryDistribution->pluck('total'),
            ],
            'statusChart' => [
                'labels' => $statusLabels,
                'values' => collect($statusLabels)->map(fn (string $status) => (int) ($statusCounts[$status] ?? 0)),
            ],
            'activityChart' => [
                'labels' => $activityBreakdown->pluck('action'),
                'values' => $activityBreakdown->pluck('total'),
            ],
            'coverageChart' => $coverageForecast,
'recentAssets' => $visibility['assets'] ? Asset::with(['type'])->latest()->limit(5)->get() : collect(),
            'recentAlerts' => $visibility['notifications'] ? SystemNotification::where('in_app_visible', true)->latest()->limit(5)->get() : collect(),
            'recentActivity' => $visibility['audit'] ? AuditLog::latest()->limit(6)->get() : collect(),
            'upcomingExpiries' => $visibility['reports'] && $visibility['assets'] ? $this->upcomingExpiries() : collect(),
            'vendorRenewals' => $visibility['vendors'] ? Vendor::query()
                ->where(function ($query) {
                    $query->whereIn('amc_status', ['Renewal Due', 'Expired'])
                        ->orWhereBetween('contract_end', [today(), today()->addDays(60)]);
                })
                ->orderByRaw('contract_end IS NULL')
                ->orderBy('contract_end')
                ->limit(5)
                ->get() : collect(),
            'activityTotal' => $visibility['audit'] ? AuditLog::whereBetween('created_at', [$from, $to])->count() : 0,
        ]);
    }

    private function dateRange(array $filters): array
    {
        if (filled($filters['from'] ?? null) && filled($filters['to'] ?? null)) {
            return [
                Carbon::parse($filters['from'])->startOfDay(),
                Carbon::parse($filters['to'])->endOfDay(),
                'custom',
            ];
        }

        $period = $filters['period'] ?? 'month';
        $from = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->subDays(6)->startOfDay(),
            default => now()->startOfMonth(),
        };

        return [$from, now()->endOfDay(), $period];
    }

    private function coverageBuckets(string $column): array
    {
        return [
            Asset::whereNotNull($column)->whereDate($column, '<', today())->count(),
            Asset::whereBetween($column, [today(), today()->addDays(30)])->count(),
            Asset::whereBetween($column, [today()->addDays(31), today()->addDays(60)])->count(),
            Asset::whereBetween($column, [today()->addDays(61), today()->addDays(90)])->count(),
        ];
    }

    private function upcomingExpiries(): Collection
    {
        return Asset::query()
            ->where(function ($query): void {
                $query->whereBetween('warranty_expiry', [today(), today()->addDays(60)])
                    ->orWhereBetween('amc_expiry', [today(), today()->addDays(60)]);
            })
            ->get()
            ->flatMap(function (Asset $asset): array {
                $items = [];
                foreach (['Warranty' => $asset->warranty_expiry, 'AMC' => $asset->amc_expiry] as $type => $date) {
                    if ($date && $date->between(today(), today()->addDays(60))) {
                        $items[] = [
                            'asset' => $asset,
                            'type' => $type,
                            'date' => $date,
                            'days' => today()->diffInDays($date),
                        ];
                    }
                }

                return $items;
            })
            ->sortBy('date')
            ->take(6)
            ->values();
    }
}
