<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('module', 'complaints')
            ->whereIn('role', ['Asset Manager', 'Sub admin', 'Auditor'])
            ->update([
                'can_export' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('module', 'complaints')
            ->update([
                'can_export' => false,
                'updated_at' => now(),
            ]);
    }
};
