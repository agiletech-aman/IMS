<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['asset_categories', 'vendors', 'complaints'] as $tableName) {
            if (Schema::hasColumn($tableName, 'centre')) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `centre` ENUM('noida', 'lucknow') NULL DEFAULT NULL");
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['asset_categories', 'vendors', 'complaints'] as $tableName) {
            if (Schema::hasColumn($tableName, 'centre')) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `centre` ENUM('noida', 'lucknow') NOT NULL DEFAULT 'lucknow'");
            }
        }
    }
};