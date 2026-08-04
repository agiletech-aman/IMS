<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_permissions')
            ->where('module', 'users')
            ->whereIn('role', ['Asset Manager', 'Sub admin'])
            ->update([
                'can_assign' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('role_permissions')
            ->where('module', 'users')
            ->whereIn('role', ['Asset Manager', 'Sub admin'])
            ->update([
                'can_assign' => false,
                'updated_at' => now(),
            ]);
    }
};
