<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table): void {
            $table->id();
            $table->string('unique_id', 30)->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status', 20)->default('Active');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('fb_type', 30)->nullable();
            $table->string('room_number', 100)->nullable();
            $table->text('remark')->nullable();
            $table->enum('centre', ['noida', 'lucknow'])->nullable();
            $table->timestamps();
        });

        DB::table('users')->where('login_enabled', false)->orderBy('id')->get()->each(function (object $user): void {
            DB::table('faculties')->insert([
                'id' => $user->id,
                'unique_id' => $user->unique_id,
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->contact,
                'address' => $user->address,
                'image_path' => $user->image_path,
                'status' => $user->status,
                'department_id' => $user->department_id,
                'fb_type' => $user->fb_type,
                'room_number' => $user->room_number,
                'remark' => $user->remark,
                'centre' => $user->centre,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};