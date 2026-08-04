<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->boolean('can_download')->default(false)->after('can_export');
            $table->boolean('can_verify')->default(false)->after('can_download');
            $table->boolean('can_restore')->default(false)->after('can_verify');
        });

        // Existing non-administrator dashboard users must not inherit access to
        // private backup files. Administrator accounts bypass role rows.
        DB::table('role_permissions')->where('module', 'backup')->update([
            'can_view' => false,
            'can_create' => false,
            'can_update' => false,
            'can_delete' => false,
            'can_download' => false,
            'can_verify' => false,
            'can_restore' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('role_permissions', function (Blueprint $table) {
            $table->dropColumn(['can_download', 'can_verify', 'can_restore']);
        });
    }
};
