<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('backup_type', 20);
            $table->string('frequency', 20);
            $table->time('run_at')->default('02:00');
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->string('retention_type', 20)->default('count');
            $table->unsignedInteger('retention_value')->default(7);
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('backup_number', 30)->unique();
            $table->foreignId('backup_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->string('status', 20)->default('queued')->index();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('database_method', 30)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->string('created_by')->nullable();
            $table->string('created_by_email')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'type', 'status']);
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20)->default('info')->index();
            $table->string('event', 60)->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('restore_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->constrained()->restrictOnDelete();
            $table->foreignId('safety_backup_id')->nullable()->constrained('backups')->nullOnDelete();
            $table->string('status', 20)->default('queued')->index();
            $table->string('requested_by')->nullable();
            $table->string('requested_by_email')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_jobs');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('backup_schedules');
    }
};
