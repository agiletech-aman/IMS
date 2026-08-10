<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignment_histories', function (Blueprint $table) {

            $table->foreignId('user_id')
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('asset_id')
                ->after('user_id')
                ->constrained('assets')
                ->cascadeOnDelete();

            $table->timestamp('assigned_at')
                ->nullable()
                ->after('asset_id');

            $table->timestamp('unassigned_at')
                ->nullable()
                ->after('assigned_at');

            $table->string('assigned_by')
                ->nullable()
                ->after('unassigned_at');

            $table->string('unassigned_by')
                ->nullable()
                ->after('assigned_by');

            $table->text('remarks')
                ->nullable()
                ->after('unassigned_by');

            $table->index(
                ['user_id', 'assigned_at'],
                'assignment_history_user_date_idx'
            );

            $table->index(
                ['asset_id', 'assigned_at'],
                'assignment_history_asset_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignment_histories', function (Blueprint $table) {

            $table->dropIndex('assignment_history_user_date_idx');
            $table->dropIndex('assignment_history_asset_date_idx');

            $table->dropForeign(['user_id']);
            $table->dropForeign(['asset_id']);

            $table->dropColumn([
                'user_id',
                'asset_id',
                'assigned_at',
                'unassigned_at',
                'assigned_by',
                'unassigned_by',
                'remarks',
            ]);
        });
    }
};
