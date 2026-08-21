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
            Schema::hasTable('notification_preferences') &&
            !Schema::hasColumn('notification_preferences', 'centre')
        ) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }

        if (
            Schema::hasTable('system_notifications') &&
            !Schema::hasColumn('system_notifications', 'centre')
        ) {
            Schema::table('system_notifications', function (Blueprint $table) {
                $table->enum('centre', ['noida', 'lucknow'])
                    ->default('lucknow');
            });
        }

        if (
            Schema::hasTable('system_settings') &&
            !Schema::hasColumn('system_settings', 'centre')
        ) {
            Schema::table('system_settings', function (Blueprint $table) {
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
        if (Schema::hasColumn('notification_preferences', 'centre')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('system_notifications', 'centre')) {
            Schema::table('system_notifications', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }

        if (Schema::hasColumn('system_settings', 'centre')) {
            Schema::table('system_settings', function (Blueprint $table) {
                $table->dropColumn('centre');
            });
        }
    }
};