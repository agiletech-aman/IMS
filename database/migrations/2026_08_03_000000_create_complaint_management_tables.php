<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_number', 30)->unique();
            $table->string('subject');
            $table->text('description');
            $table->string('requester_name');
            $table->string('requester_email')->nullable();
            $table->string('requester_contact', 30)->nullable();
            $table->string('category', 80);
            $table->string('priority', 20)->default('Medium');
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('engineer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('Complaint Raised');
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('visit_scheduled_at')->nullable();
            $table->dateTime('visit_started_at')->nullable();
            $table->dateTime('work_started_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority']);
        });

        Schema::create('complaint_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->string('performed_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_activities');
        Schema::dropIfExists('complaints');
    }
};
