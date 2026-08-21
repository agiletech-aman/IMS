<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            Schema::hasTable('role_permissions') &&
            !Schema::hasColumn('role_permissions', 'centre')
        ) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('role_permissions', 'centre')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }
    }
};