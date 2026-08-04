<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('head_name')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('sub_departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('manager_name')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('country')->nullable();
            $table->string('support_contact')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag', 50)->unique();
            $table->string('name');
            $table->foreignId('asset_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('asset_type_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->date('installation_date')->nullable();
            $table->string('location')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('status', 30)->default('In Stock');
            $table->date('warranty_expiry')->nullable();
            $table->date('amc_expiry')->nullable();
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('asset_categories');
        Schema::dropIfExists('sub_departments');
        Schema::dropIfExists('departments');
    }
};
