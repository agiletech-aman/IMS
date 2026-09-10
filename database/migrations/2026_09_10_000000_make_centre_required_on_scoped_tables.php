<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'assets',
        'departments',
        'sub_departments',
        'asset_types',
        'brands',
        'asset_categories',
        'vendors',
        'faculties',
        'asset_subtypes',
    ];

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $tableName) {
            if (! Schema::hasColumn($tableName, 'centre')) {
                continue;
            }

            // Legacy/unmapped rows created before a centre was required.
            // Backfill them so the NOT NULL constraint below can be applied
            // without dropping data; review and correct these manually if the
            // guessed centre is wrong.
            DB::table($tableName)->whereNull('centre')->update(['centre' => 'lucknow']);

            DB::statement("ALTER TABLE `{$tableName}` MODIFY `centre` ENUM('noida', 'lucknow') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->tables as $tableName) {
            if (Schema::hasColumn($tableName, 'centre')) {
                DB::statement("ALTER TABLE `{$tableName}` MODIFY `centre` ENUM('noida', 'lucknow') NULL DEFAULT NULL");
            }
        }
    }
};
