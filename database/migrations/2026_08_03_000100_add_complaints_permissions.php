<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'Asset Manager' => ['view', 'create', 'update', 'assign'],
            'Sub admin' => ['view', 'create', 'update', 'delete', 'assign'],
            'Auditor' => ['view'],
            'Viewer' => ['view', 'create'],
        ];

        foreach ($defaults as $role => $actions) {
            DB::table('role_permissions')->updateOrInsert(
                ['role' => $role, 'module' => 'complaints'],
                [
                    'can_view' => in_array('view', $actions, true),
                    'can_create' => in_array('create', $actions, true),
                    'can_update' => in_array('update', $actions, true),
                    'can_delete' => in_array('delete', $actions, true),
                    'can_assign' => in_array('assign', $actions, true),
                    'can_import' => false,
                    'can_export' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('module', 'complaints')->delete();
    }
};
