<?php

return [
    [
        'id' => 'assetManagement',
        'label' => 'Asset Management',
        'icon' => 'fa-laptop-file',
        'items' => [
            ['asset-management/departments*', 'fa-building', 'Department', 'asset-management.departments.index', [], 'departments'],
            ['asset-management/sub-departments*', 'fa-sitemap', 'Sub Department', 'asset-management.sub-departments.index', [], 'sub_departments'],
            ['asset-management/types*', 'fa-shapes', 'Type', 'asset-management.types.index', [], 'types'],
            ['asset-management/sub-types*', 'fa-layer-group', 'Subtype', 'asset-management.sub-types.index', [], 'sub_types'],
            ['asset-management/brands*', 'fa-copyright', 'Brand', 'asset-management.brands.index', [], 'brands'],
            ['assets*', 'fa-list-check', 'Assets', 'assets.index', [], 'assets'],
        ],
    ],

    [
        'id' => 'usersDirectory',
        'label' => 'User Management',
        'icon' => 'fa-users',
        'items' => [
            ['users*', 'fa-users', 'Users', 'users.index', [], 'faculty'],
        ],
    ],

    [
        'id' => 'userManagement',
        'label' => 'Access Accounts',
        'icon' => 'fa-user-gear',
        'items' => [
            ['access-accounts*', 'fa-user-tie', 'Manager', 'access-accounts.index', ['role' => 'Asset Manager'], 'access_accounts_asset_manager'],
            ['access-accounts*', 'fa-user-ninja', 'Admin', 'access-accounts.index', ['role' => 'Sub admin'], 'access_accounts_sub_admin'],
            ['access-accounts*', 'fa-user-shield', 'Auditor', 'access-accounts.index', ['role' => 'Auditor'], 'access_accounts_auditor'],
            ['access-accounts*', 'fa-user-lock', 'Viewer', 'access-accounts.index', ['role' => 'Viewer'], 'access_accounts_viewer'],
        ],
    ],

    [
        'id' => 'governance',
        'label' => 'Data & Audit',
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
