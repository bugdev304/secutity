<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        // Impede fator duplicado para o mesmo contato (mesmo user_id+type+identifier).
        // NULL (identifier de fatores TOTP) não colide entre si — múltiplos autenticadores continuam permitidos.
        Schema::table("{$tablePrefix}factors", function (Blueprint $table): void {
            $table->unique(['user_id', 'type', 'identifier']);
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::table("{$tablePrefix}factors", function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'type', 'identifier']);
        });
    }
};
