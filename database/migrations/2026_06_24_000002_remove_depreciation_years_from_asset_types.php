<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('asset_types', 'depreciation_years')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->dropColumn('depreciation_years');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asset_types', 'depreciation_years')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->unsignedSmallInteger('depreciation_years')->nullable();
            });
        }
    }
};
