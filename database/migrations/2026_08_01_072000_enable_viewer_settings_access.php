<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('role', 'Viewer')
            ->where('module', 'settings')
            ->update([
                'can_view' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('role', 'Viewer')
            ->where('module', 'settings')
            ->update([
                'can_view' => false,
                'updated_at' => now(),
            ]);
    }
};
