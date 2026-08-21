<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['asset_categories', 'vendors', 'complaints'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'centre')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->enum('centre', ['noida', 'lucknow'])->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['asset_categories', 'vendors', 'complaints'] as $tableName) {
            if (Schema::hasColumn($tableName, 'centre')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('centre');
                });
            }
        }
    }
};