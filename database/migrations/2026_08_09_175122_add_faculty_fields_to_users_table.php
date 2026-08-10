<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('unique_id')
                    ->constrained('departments')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'fb_type')) {
                $table->string('fb_type', 30)
                    ->nullable()
                    ->after('department_id');
            }

            if (!Schema::hasColumn('users', 'room_number')) {
                $table->string('room_number', 100)
                    ->nullable()
                    ->after('fb_type');
            }

            if (!Schema::hasColumn('users', 'remark')) {
                $table->text('remark')
                    ->nullable()
                    ->after('address');
            }

            $table->string('email')
                ->nullable()
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
            }

            $table->dropColumn([
                'department_id',
                'fb_type',
                'room_number',
                'remark',
            ]);

            $table->string('email')
                ->nullable(false)
                ->change();
        });
    }
};
