<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone', 30)->nullable();
            $table->string('designation')->nullable();
            $table->text('address')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status', 20)->default('Active')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        DB::table('admins')->insert([
            'name' => 'Arjun Sharma',
            'email' => 'admin@nexacore.com',
            'password' => Hash::make('password'),
            'designation' => 'System Administrator',
            'status' => 'Active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
