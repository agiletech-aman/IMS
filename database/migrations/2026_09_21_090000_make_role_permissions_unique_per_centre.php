<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every existing row today is implicitly centre = 'lucknow' (the
     * column's DB default; the app has never set it explicitly) and was
     * applied to both centres identically. Duplicate each row into
     * centre = 'noida' first so both centres start identical to today's
     * shared behaviour — nothing regresses the moment centre filtering
     * turns on — before the unique key is tightened to include centre.
     */
    public function up(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->dropUnique('role_permissions_role_module_unique');
        });

        if (! DB::table('role_permissions')->where('centre', 'noida')->exists()) {
            $rows = DB::table('role_permissions')->where('centre', 'lucknow')->get();

            $duplicates = $rows->map(function ($row) {
                $row = (array) $row;
                unset($row['id']);
                $row['centre'] = 'noida';

                return $row;
            })->all();

            if ($duplicates !== []) {
                DB::table('role_permissions')->insert($duplicates);
            }
        }

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->unique(['role', 'module', 'centre'], 'role_permissions_role_module_centre_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('role_permissions')) {
            return;
        }

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->dropUnique('role_permissions_role_module_centre_unique');
        });

        DB::table('role_permissions')->where('centre', 'noida')->delete();

        Schema::table('role_permissions', function (Blueprint $table): void {
            $table->unique(['role', 'module'], 'role_permissions_role_module_unique');
        });
    }
};
