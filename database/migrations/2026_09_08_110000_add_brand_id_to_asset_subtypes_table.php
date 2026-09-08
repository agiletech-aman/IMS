<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_subtypes', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('asset_type_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_subtypes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
