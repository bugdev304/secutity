<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Decisão P2.B: tabela user_state 1:1 com users.
// Mantém a tabela users da app limpa; custo de 1 JOIN por login.

return new class extends Migration
{
    public function up(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';
        $usersTable = $isPgsql ? 'public.users' : 'users';

        Schema::create("{$tablePrefix}user_state", function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('account_locked_at')->nullable();
            $table->unsignedBigInteger('account_unlocked_by_user_id')->nullable();
            $table->timestamp('account_unlocked_at')->nullable();
            $table->boolean('must_register_factor')->default(false);
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on($usersTable)
                ->cascadeOnDelete();

            $table->foreign('account_unlocked_by_user_id')
                ->references('id')
                ->on($usersTable)
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}user_state");
    }
};
