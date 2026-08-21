<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class UniqueCodeGenerator
{
    /**
     * Generate a unique code from a shared sequence.
     *
     * @param  string       $key     Sequence key in code_sequences table.
     * @param  string       $prefix  Code prefix, e.g. 'ID'.
     * @param  array|string $tables  One table name, or an array of table names to check uniqueness across.
     * @param  string       $column  Column name to check (same in all tables).
     */
    public static function generate(string $key, string $prefix, array|string $tables, string $column): string
    {
        $tables = is_array($tables) ? $tables : [$tables];

        return DB::transaction(function () use ($key, $prefix, $tables, $column): string {
            DB::table('code_sequences')->insertOrIgnore([
                'key' => $key,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('code_sequences')->where('key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->next_number;
            $code = $prefix.'-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);

            while (self::existsInAnyTable($tables, $column, $code)) {
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

    private static function existsInAnyTable(array $tables, string $column, string $code): bool
    {
        foreach ($tables as $table) {
            if (DB::table($table)->where($column, $code)->exists()) {
                return true;
            }
        }

        return false;
    }
}