<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_types') || ! Schema::hasColumn('asset_types', 'asset_category_id')) {
            return;
        }

        Schema::table('asset_types', function (Blueprint $table): void {
            $table->dropForeign(['asset_category_id']);
            $table->dropColumn('asset_category_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_types') || Schema::hasColumn('asset_types', 'asset_category_id')) {
            return;
        }

        Schema::table('asset_types', function (Blueprint $table): void {
            $table->foreignId('asset_category_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
