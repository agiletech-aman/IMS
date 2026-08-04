<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $features = ['settings_basic', 'settings_advanced'];
        $defaults = [
            'Asset Manager' => [
                'settings_basic' => ['view'],
            ],
            'Sub admin' => [
                'settings_basic' => ['view', 'update'],
                'settings_advanced' => ['view', 'update'],
            ],
            'Auditor' => [
                'settings_basic' => ['view'],
            ],
            'Viewer' => [
                'settings_basic' => ['view'],
            ],
        ];

        foreach ($defaults as $role => $permissions) {
            DB::table('role_permissions')
                ->where('role', $role)
                ->where('module', 'settings')
                ->update([
                    'can_view' => true,
                    'can_update' => false,
                    'updated_at' => now(),
                ]);

            foreach ($features as $feature) {
                $actions = $permissions[$feature] ?? [];
                DB::table('role_permissions')->updateOrInsert(
                    ['role' => $role, 'module' => $feature],
                    [
                        'can_view' => in_array('view', $actions, true),
                        'can_create' => false,
                        'can_update' => in_array('update', $actions, true),
                        'can_delete' => false,
                        'can_assign' => false,
                        'can_import' => false,
                        'can_export' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->whereIn('module', ['settings_basic', 'settings_advanced'])
            ->delete();
    }
};
