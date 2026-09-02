<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('faculties')
            ->where('unique_id', 'like', 'FAC-%')
            ->orderBy('id')
            ->get(['id', 'unique_id'])
            ->each(function (object $faculty): void {
                DB::table('faculties')
                    ->where('id', $faculty->id)
                    ->update(['unique_id' => 'USR-'.Str::after($faculty->unique_id, 'FAC-')]);
            });
    }

    public function down(): void
    {
        DB::table('faculties')
            ->where('unique_id', 'like', 'USR-%')
            ->orderBy('id')
            ->get(['id', 'unique_id'])
            ->each(function (object $faculty): void {
                DB::table('faculties')
                    ->where('id', $faculty->id)
                    ->update(['unique_id' => 'FAC-'.Str::after($faculty->unique_id, 'USR-')]);
            });
    }
};
