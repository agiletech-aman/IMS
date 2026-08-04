<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class UniqueCodeGenerator
{
    public static function generate(string $key, string $prefix, string $table, string $column): string
    {
        return DB::transaction(function () use ($key, $prefix, $table, $column): string {
            DB::table('code_sequences')->insertOrIgnore([
                'key' => $key,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('code_sequences')->where('key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->next_number;
            $code = $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);

            while (DB::table($table)->where($column, $code)->exists()) {
                $number++;
                $code = $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            }

            DB::table('code_sequences')->where('key', $key)->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

            return $code;
        }, 3);
    }
}
