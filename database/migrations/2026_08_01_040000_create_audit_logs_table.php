<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_name')->default('System')->index();
            $table->string('actor_email')->nullable()->index();
            $table->string('actor_role')->nullable();
            $table->string('action', 40)->index();
            $table->string('module', 80)->index();
            $table->string('description');
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('route_name')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('result', 20)->default('Success')->index();
            $table->timestamps();

            $table->index(['created_at', 'module', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
