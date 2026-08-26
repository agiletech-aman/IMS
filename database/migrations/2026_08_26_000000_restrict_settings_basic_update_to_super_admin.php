<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Super admin (session role "Administrator") bypasses role_permissions entirely
        // via PermissionService::isAdministrator(), so it needs no row here. Every other
        // role should only be able to view Basic Settings, never update them.
        DB::table('role_permissions')
            ->where('module', 'settings_basic')
            ->update(['can_update' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('role', 'Sub admin')
            ->where('module', 'settings_basic')
            ->update(['can_update' => true, 'updated_at' => now()]);
    }
};
