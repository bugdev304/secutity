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
        $usersTable = $isPgsql ? 'public.users' : 'users';

        Schema::create("{$tablePrefix}factors", function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32);                    // FactorType backed string
            $table->string('identifier')->nullable();      // e-mail/phone snapshot; null para TOTP
            $table->text('secret_encrypted')->nullable();  // TOTP seed — cast encrypted; nunca expor
            $table->string('name')->nullable();
            $table->timestamp('confirmed_at')->nullable(); // null = cadastro pendente de confirmação
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on($usersTable)
                ->cascadeOnDelete();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}factors");
    }
};
