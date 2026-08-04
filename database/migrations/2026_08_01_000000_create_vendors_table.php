<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('vendor_type', 30);
            $table->string('category')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('amc_status', 30)->default('Not Applicable');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('preferred')->default(false);
            $table->decimal('rating', 2, 1)->nullable();
            $table->string('status', 20)->default('Active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['vendor_type', 'status']);
            $table->index('amc_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
