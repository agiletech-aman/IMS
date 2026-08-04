<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('encryption', 20)->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->text('notification_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->unique();
            $table->string('label');
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->unsignedSmallInteger('days_before')->nullable();
            $table->timestamps();
        });

        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->index();
            $table->string('title');
            $table->text('message');
            $table->string('severity', 20)->default('info')->index();
            $table->string('module', 80);
            $table->json('data')->nullable();
            $table->string('unique_key')->nullable()->unique();
            $table->boolean('in_app_visible')->default(true)->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('emailed_at')->nullable();
            $table->text('email_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('smtp_settings');
    }
};
