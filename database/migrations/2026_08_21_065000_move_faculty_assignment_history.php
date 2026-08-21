<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('asset_assignment_histories') || ! Schema::hasColumn('asset_assignment_histories', 'user_id')) {
            return;
        }

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->renameColumn('user_id', 'faculty_id');
        });

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->foreign('faculty_id')->references('id')->on('faculties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('asset_assignment_histories') || ! Schema::hasColumn('asset_assignment_histories', 'faculty_id')) {
            return;
        }

        Schema::table('asset_assignment_histories', function (Blueprint $table): void {
            $table->dropForeign(['faculty_id']);
            $table->renameColumn('faculty_id', 'user_id');
        });
    }
};