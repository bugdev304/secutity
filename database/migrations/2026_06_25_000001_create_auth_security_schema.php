<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = config('auth-security.schema', 'auth_security');

        // Schema só existe no PostgreSQL; SQLite usa tabelas sem prefixo
        if (DB::getDriverName() === 'pgsql' && $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS \"{$schema}\"");
        }
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');

        if (DB::getDriverName() === 'pgsql' && $schema) {
            DB::statement("DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE");
        }
    }
};
