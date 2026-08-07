<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Remove old fields
            |--------------------------------------------------------------------------
            */

            // asset_category_id par foreign key hai to pehle remove hogi
            $table->dropForeign(['asset_category_id']);

            $table->dropColumn([
                'asset_category_id',
                'model',
                'purchase_date',
                'location',
            ]);
        });

        Schema::table('assets', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | Add new fields
            |--------------------------------------------------------------------------
            */

            $table->string('cpu')->nullable()->after('brand_id');
            $table->string('hdd')->nullable()->after('cpu');
            $table->string('ram')->nullable()->after('hdd');
            $table->string('operating_system')->nullable()->after('ram');

            $table->string('fr_number')
                ->nullable()
                ->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'cpu',
                'hdd',
                'ram',
                'operating_system',
                'fr_number',
            ]);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('asset_category_id')
                ->nullable()
                ->constrained('asset_categories')
                ->nullOnDelete();

            $table->string('model')->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('location')->nullable();
        });
    }
};