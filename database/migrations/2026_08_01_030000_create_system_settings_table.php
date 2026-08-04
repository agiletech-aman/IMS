<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('application_name')->default('Agile Tech Solutions IIM');
            $table->string('language', 20)->default('en');
            $table->string('timezone', 60)->default('Asia/Kolkata');
            $table->string('date_format', 30)->default('d M Y');
            $table->string('currency', 10)->default('INR');
            $table->string('company_name')->default('Agile Tech Solutions');
            $table->string('tax_number')->nullable();
            $table->text('registered_address')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 30)->nullable();
            $table->unsignedSmallInteger('session_timeout')->default(30);
            $table->unsignedSmallInteger('password_expiry_days')->nullable()->default(90);
            $table->boolean('require_mfa')->default(false);
            $table->boolean('strong_password')->default(true);
            $table->boolean('restrict_concurrent_sessions')->default(false);
            $table->string('default_theme', 20)->default('light');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
