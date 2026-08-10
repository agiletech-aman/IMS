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
            ['asset-management/brands*', 'fa-copyright', 'Brand', 'asset-management.brands.index', [], 'brands'],
            ['assets*', 'fa-list-check', 'Assets', 'assets.index', [], 'assets'],
        ],
    ],

    [
        'id' => 'userManagement',
        'label' => 'User Management',
        'icon' => 'fa-user-gear',
        'items' => [
            ['users*', 'fa-users', 'Faculty', 'users.index', [], 'users'],
            ['users*', 'fa-user-tie', 'Asset Manager', 'users.index', ['role' => 'Asset Manager'], 'users'],
            ['users*', 'fa-user-ninja', 'Sub admin', 'users.index', ['role' => 'Sub admin'], 'users'],
            ['users*', 'fa-user-shield', 'Auditor', 'users.index', ['role' => 'Auditor'], 'users'],
            ['users*', 'fa-user-lock', 'Viewer', 'users.index', ['role' => 'Viewer'], 'users'],
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
