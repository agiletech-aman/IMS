<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('unique_id', 30)->nullable()->unique()->after('id');
            $table->string('contact', 30)->nullable()->after('email');
            $table->text('address')->nullable()->after('contact');
            $table->string('image_path')->nullable()->after('address');
            $table->string('status', 20)->default('Active')->after('image_path');
        });

        DB::table('users')->orderBy('id')->get(['id'])->each(function (object $user, int $index): void {
            DB::table('users')->where('id', $user->id)->update([
                'unique_id' => 'USR-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['unique_id']);
            $table->dropColumn(['unique_id', 'contact', 'address', 'image_path', 'status']);
        });
    }
};
