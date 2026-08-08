<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('module', 60);
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_assign')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_export')->default(false);
            $table->timestamps();
            $table->unique(['role', 'module']);
        });

        $modules = [
            'dashboard', 'assets', 'departments', 'sub_departments',
            'types', 'brands', 'users', 'vendors', 'notifications', 'reports',
            'audit_logs', 'backup', 'settings', 'settings_basic', 'settings_advanced',
            'roles_permissions', 'administrators',
        ];
        $defaults = [
            'Asset Manager' => [
                'dashboard' => ['view'],
                'assets' => ['view', 'create', 'update', 'delete', 'assign', 'import', 'export'],
                'departments' => ['view', 'create', 'update'],
                'sub_departments' => ['view', 'create', 'update'],
                'types' => ['view', 'create', 'update'],
                'brands' => ['view', 'create', 'update'],
                'users' => ['view', 'create', 'update', 'delete'],
                'vendors' => ['view', 'create', 'update', 'delete'],
                'notifications' => ['view', 'update', 'delete'],
                'reports' => ['view', 'create', 'export'],
                'settings' => ['view'],
                'settings_basic' => ['view'],
            ],
            'Sub admin' => [
                'dashboard' => ['view'],
                'assets' => ['view', 'create', 'update', 'delete', 'assign', 'import', 'export'],
                'departments' => ['view', 'create', 'update', 'delete'],
                'sub_departments' => ['view', 'create', 'update', 'delete'],
                'types' => ['view', 'create', 'update', 'delete'],
                'brands' => ['view', 'create', 'update', 'delete'],
                'users' => ['view', 'create', 'update', 'delete'],
                'vendors' => ['view', 'create', 'update', 'delete'],
                'notifications' => ['view', 'update', 'delete'],
                'reports' => ['view', 'create', 'export'],
                'audit_logs' => ['view', 'export'],
                'backup' => ['view'],
                'settings' => ['view'],
                'settings_basic' => ['view', 'update'],
                'settings_advanced' => ['view', 'update'],
                'roles_permissions' => ['view'],
            ],
            'Auditor' => [
                'dashboard' => ['view'],
                'assets' => ['view', 'export'],
                'departments' => ['view'],
                'sub_departments' => ['view'],
                'types' => ['view'],
                'brands' => ['view'],
                'users' => ['view'],
                'vendors' => ['view'],
                'notifications' => ['view'],
                'reports' => ['view', 'export'],
                'audit_logs' => ['view', 'export'],
                'roles_permissions' => ['view'],
                'settings' => ['view'],
                'settings_basic' => ['view'],
            ],
            'Viewer' => [
                'dashboard' => ['view'],
                'assets' => ['view'],
                'notifications' => ['view', 'update'],
                'reports' => ['view', 'export'],
                'settings' => ['view'],
                'settings_basic' => ['view'],
            ],
        ];

        $rows = [];
        foreach ($defaults as $role => $rolePermissions) {
            foreach ($modules as $module) {
                $actions = $rolePermissions[$module] ?? [];
                $rows[] = [
                    'role' => $role,
                    'module' => $module,
                    'can_view' => in_array('view', $actions, true),
                    'can_create' => in_array('create', $actions, true),
                    'can_update' => in_array('update', $actions, true),
                    'can_delete' => in_array('delete', $actions, true),
                    'can_assign' => in_array('assign', $actions, true),
                    'can_import' => in_array('import', $actions, true),
                    'can_export' => in_array('export', $actions, true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('role_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
