<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('asset_subtype_id')->nullable()->after('asset_type_id')->constrained()->nullOnDelete();
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['cpu', 'hdd', 'ram', 'operating_system']);
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('cpu')->nullable()->after('brand_id');
            $table->string('hdd')->nullable()->after('cpu');
            $table->string('ram')->nullable()->after('hdd');
            $table->string('operating_system')->nullable()->after('ram');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_subtype_id');
        });
    }
};
