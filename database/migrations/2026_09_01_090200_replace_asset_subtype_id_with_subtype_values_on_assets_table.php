<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_subtype_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->json('subtype_values')->nullable()->after('asset_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('subtype_values');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('asset_subtype_id')->nullable()->after('asset_type_id')->constrained()->nullOnDelete();
        });
    }
};
