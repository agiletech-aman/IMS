<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_subtypes', function (Blueprint $table) {
            $table->json('parameter_values')->nullable()->after('is_required');
        });
    }

    public function down(): void
    {
        Schema::table('asset_subtypes', function (Blueprint $table) {
            $table->dropColumn('parameter_values');
        });
    }
};
