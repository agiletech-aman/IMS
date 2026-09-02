<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_subtypes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->enum('centre', ['noida', 'lucknow'])->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_subtypes');
    }
};
