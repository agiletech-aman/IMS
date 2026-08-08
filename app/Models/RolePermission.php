<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    public const ACTIONS = [
        'view', 'create', 'update', 'delete', 'assign', 'import', 'export',
        'download', 'verify', 'restore',
    ];

    public const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'actions' => ['view']],
        'assets' => ['label' => 'Assets', 'icon' => 'fa-laptop-file', 'actions' => self::ACTIONS],
        'departments' => ['label' => 'Departments', 'icon' => 'fa-building', 'actions' => ['view', 'create', 'update', 'delete']],
        'sub_departments' => ['label' => 'Sub Departments', 'icon' => 'fa-sitemap', 'actions' => ['view', 'create', 'update', 'delete']],
        'types' => ['label' => 'Asset Types', 'icon' => 'fa-shapes', 'actions' => ['view', 'create', 'update', 'delete']],
        'brands' => ['label' => 'Brands', 'icon' => 'fa-copyright', 'actions' => ['view', 'create', 'update', 'delete']],
        'users' => ['label' => 'Users & Access Accounts', 'icon' => 'fa-users', 'actions' => ['view', 'create', 'update', 'delete', 'assign']],
        'vendors' => ['label' => 'Vendors / OEM', 'icon' => 'fa-handshake', 'actions' => ['view', 'create', 'update', 'delete']],
        'complaints' => ['label' => 'Complaint Management', 'icon' => 'fa-screwdriver-wrench', 'actions' => ['view', 'create', 'update', 'delete', 'assign', 'export']],
        'notifications' => ['label' => 'Notifications & Alerts', 'icon' => 'fa-bell', 'actions' => ['view', 'update', 'delete']],
        'reports' => ['label' => 'Reports', 'icon' => 'fa-file-lines', 'actions' => ['view', 'create', 'export']],
        'audit_logs' => ['label' => 'Audit Logs', 'icon' => 'fa-clock-rotate-left', 'actions' => ['view', 'export']],
        'backup' => [
            'label' => 'Backup & Recovery',
            'icon' => 'fa-database',
            'actions' => ['view', 'create', 'update', 'delete', 'download', 'verify', 'restore'],
        ],
        'settings' => ['label' => 'Settings Module Access', 'icon' => 'fa-gear', 'actions' => ['view']],
        'settings_basic' => ['label' => 'Settings: Basic', 'icon' => 'fa-sliders', 'actions' => ['view', 'update']],
        'settings_advanced' => ['label' => 'Settings: Advanced', 'icon' => 'fa-shield-halved', 'actions' => ['view', 'update']],
        'roles_permissions' => ['label' => 'Roles & Permissions', 'icon' => 'fa-user-shield', 'actions' => ['view', 'update']],
        'administrators' => ['label' => 'Administrators', 'icon' => 'fa-user-gear', 'actions' => ['view', 'create', 'update', 'delete']],
    ];

    protected $fillable = [
        'role', 'module', 'can_view', 'can_create', 'can_update',
        'can_delete', 'can_assign', 'can_import', 'can_export',
        'can_download', 'can_verify', 'can_restore',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
            'can_assign' => 'boolean',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_download' => 'boolean',
            'can_verify' => 'boolean',
            'can_restore' => 'boolean',
        ];
    }
}

