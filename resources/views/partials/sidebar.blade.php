@php
$permissionService = app(\App\Services\PermissionService::class);
$groups = [
    [
        'id' => 'assetManagement',
        'label' => 'Asset Management',
        'icon' => 'fa-laptop-file',
        'items' => [
            ['asset-management/departments*', 'fa-building', 'Department', 'asset-management.departments.index', [], 'departments'],
            ['asset-management/sub-departments*', 'fa-sitemap', 'Sub Department', 'asset-management.sub-departments.index', [], 'sub_departments'],
            ['asset-management/categories*', 'fa-tags', 'Category', 'asset-management.categories.index', [], 'categories'],
            ['asset-management/types*', 'fa-shapes', 'Type', 'asset-management.types.index', [], 'types'],
            ['asset-management/brands*', 'fa-copyright', 'Brand', 'asset-management.brands.index', [], 'brands'],
            ['assets*', 'fa-list-check', 'Assets', 'assets.index', [], 'assets'],
        ],
    ],
    [
        'id' => 'userManagement',
        'label' => 'User Management',
        'icon' => 'fa-user-gear',
        'items' => [
            ['users*', 'fa-users', 'Users', 'users.index', [], 'users'],
            ['users*', 'fa-user-tie', 'Asset Manager', 'users.index', ['role' => 'Asset Manager'], 'users'],
            ['users*', 'fa-user-ninja', 'Sub admin', 'users.index', ['role' => 'Sub admin'], 'users'],
            ['users*', 'fa-user-shield', 'Auditor', 'users.index', ['role' => 'Auditor'], 'users'],
            ['users*', 'fa-user-lock', 'Viewer', 'users.index', ['role' => 'Viewer'], 'users'],
        ],
    ],
    [
        'id' => 'serviceManagement',
        'label' => 'Service Management',
        'icon' => 'fa-headset',
        'items' => [
            ['complaints*', 'fa-screwdriver-wrench', 'Complaint Management', 'complaints.index', [], 'complaints'],
            ['notifications*', 'fa-bell', 'Notifications & Alerts', 'notifications.index', [], 'notifications'],
            ['vendors*', 'fa-handshake', 'Vendors / OEM', 'vendors.index', [], 'vendors'],
        ],
    ],
   
    [
        'id' => 'governance',
        'label' => 'Governance',
        'icon' => 'fa-chart-column',
        'items' => [
            ['reports*', 'fa-file-lines', 'Reports', 'reports.index', [], 'reports'],
            ['audit-logs*', 'fa-clock-rotate-left', 'Audit Logs', 'audit-logs.index', [], 'audit_logs'],
            ['backup*', 'fa-database', 'Backup & Recovery', 'backup.index', [], 'backup'],
        ],
    ],
    [
        'id' => 'administration',
        'label' => 'Administration',
        'icon' => 'fa-user-gear',
        'items' => [
            ['roles-permissions*', 'fa-user-shield', 'Roles & Permissions', 'roles-permissions.index', [], 'roles_permissions'],
            ['settings*', 'fa-gear', 'Settings', 'settings.index', [], 'settings'],
        ],
    ],
];
@endphp

<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('dashboard') }}">
        <span class="sidebar-brand-icon">
            <img
                src="{{ \App\Support\PublicUrl::asset('static/img/agile-tech-logo.png') }}"
                alt=""
            >
        </span>
        <span class="brand-copy sidebar-brand-copy">
            <strong>Agile Tech</strong>
            <small>Solutions</small>
        </span>
    </a>

    <nav class="sidebar-nav">
        <div class="recently-opened" id="recentlyOpened" hidden>
            <span class="nav-caption">Recently Opened</span>
            <div id="recentlyOpenedLinks"></div>
        </div>

        <span class="nav-caption">Workspace</span>

        @if($permissionService->allows('dashboard'))
        <a href="{{ route('dashboard') }}"
           class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}"
           title="Dashboard">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>
        @endif

        @foreach($groups as $group)
            @php
                $visibleItems = collect($group['items'])->filter(fn ($item) => $permissionService->allows($item[5]));
                $groupActive = $visibleItems->contains(function ($item) {
                    return $item[0] !== '#' && request()->is($item[0]);
                });
            @endphp

            @if($visibleItems->isNotEmpty())
            <div class="nav-group {{ $groupActive ? 'is-active' : '' }}">
                <button class="nav-group-toggle {{ $groupActive ? 'active' : '' }}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#{{ $group['id'] }}"
                        aria-expanded="{{ $groupActive ? 'true' : 'false' }}"
                        aria-controls="{{ $group['id'] }}">
                    <i class="fa-solid {{ $group['icon'] }} group-icon"></i>
                    <span>{{ $group['label'] }}</span>
                    <i class="fa-solid fa-chevron-down group-chevron"></i>
                </button>

                <div class="collapse nav-submenu {{ $groupActive ? 'show' : '' }}"
                     id="{{ $group['id'] }}"
                     data-bs-parent=".sidebar-nav">

                    @foreach($visibleItems as $item)
                        @php
                            [$pattern, $icon, $label, $routeName] = array_slice($item, 0, 4);
                            $routeParameters = $item[4] ?? [];
                            $itemRole = $routeParameters['role'] ?? null;
                            $isActive = $pattern !== '#'
                                && request()->is($pattern)
                                && ($itemRole ? request('role') === $itemRole : !request('role'));
                            $url = ($routeName !== '#' && Route::has($routeName))
                                ? route($routeName, $routeParameters)
                                : '#';
                        @endphp

                        <a href="{{ $url }}"
                           class="nav-link {{ $isActive ? 'active' : '' }} {{ $url === '#' ? 'disabled-link' : '' }}"
                           title="{{ $label }}">
                            <i class="fa-solid {{ $icon }}"></i>
                            <span>{{ $label }}</span>
                        </a>
                    @endforeach

                </div>
            </div>
            @endif
        @endforeach
    </nav>

    <div class="sidebar-help">
        <i class="fa-regular fa-circle-question"></i>
        <div>
            <strong>Need help?</strong>
            <small>Contact IT support</small>
        </div>
    </div>
</aside>
