<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->char('unique_id', 36)->nullable()->after('address');
        });

        // Backfill unique_id for existing rows
        DB::table('users')->whereNull('unique_id')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('users')->where('id', $row->id)->update(['unique_id' => (string) Str::uuid()]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->char('unique_id', 36)->nullable(false)->change();
            $table->unique('unique_id', 'uq_users_unique_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('uq_users_unique_id');
            $table->dropColumn(['unique_id', 'address', 'contact']);
        });
    }
};