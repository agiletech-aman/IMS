<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->dropUnique('notification_preferences_event_type_unique');
                $table->unique(['event_type', 'centre'], 'notification_preferences_event_type_centre_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            Schema::table('notification_preferences', function (Blueprint $table): void {
                $table->dropUnique('notification_preferences_event_type_centre_unique');
                $table->unique('event_type', 'notification_preferences_event_type_unique');
            });
        }
    }
};