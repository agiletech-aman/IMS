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
        if (Schema::hasTable('audit_logs') && !Schema::hasColumn('audit_logs', 'centre')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }

        if (Schema::hasTable('backups') && !Schema::hasColumn('backups', 'centre')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }

        if (Schema::hasTable('backup_logs') && !Schema::hasColumn('backup_logs', 'centre')) {
            Schema::table('backup_logs', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }

        if (Schema::hasTable('backup_schedules') && !Schema::hasColumn('backup_schedules', 'centre')) {
            Schema::table('backup_schedules', function (Blueprint $table) {
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
        if (Schema::hasColumn('audit_logs', 'centre')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('backups', 'centre')) {
            Schema::table('backups', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('backup_logs', 'centre')) {
            Schema::table('backup_logs', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('backup_schedules', 'centre')) {
            Schema::table('backup_schedules', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }
    }
};