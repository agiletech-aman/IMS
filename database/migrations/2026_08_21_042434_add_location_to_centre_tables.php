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
        if (!Schema::hasColumn('departments', 'centre')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('sub_departments', 'centre')) {
            Schema::table('sub_departments', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('asset_types', 'centre')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('brands', 'centre')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('assets', 'centre')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('departments', 'centre')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('sub_departments', 'centre')) {
            Schema::table('sub_departments', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('asset_types', 'centre')) {
            Schema::table('asset_types', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('brands', 'centre')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('assets', 'centre')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }
    }
};
