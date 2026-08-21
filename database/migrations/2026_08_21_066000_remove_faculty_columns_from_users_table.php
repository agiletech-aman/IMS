<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
            }
            if (Schema::hasColumn('users', 'unique_id')) {
                $table->dropUnique('users_unique_id_unique');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $columns = array_values(array_filter([
                'unique_id', 'contact', 'address', 'image_path',
                'fb_type', 'room_number', 'remark', 'department_id',
            ], fn (string $column): bool => Schema::hasColumn('users', $column)));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('unique_id', 30)->nullable()->unique();
            $table->string('contact', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('image_path')->nullable();
            $table->string('fb_type', 30)->nullable();
            $table->string('room_number', 100)->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
        });
    }
};