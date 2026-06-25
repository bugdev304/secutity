<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Decisão P2.B: tabela auth_security.user_state 1:1 com users.
// Mantém public.users limpo; custo de 1 JOIN por login.

return new class extends Migration
{
    public function up(): void
    {
        $schema = config('auth-security.schema', 'auth_security');

        Schema::create("{$schema}.user_state", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamp('password_changed_at')->nullable(); // null até a primeira troca registrada
            $table->timestamp('account_locked_at')->nullable();
            $table->unsignedBigInteger('account_unlocked_by_user_id')->nullable();
            $table->timestamp('account_unlocked_at')->nullable();
            $table->boolean('must_register_factor')->default(false); // TEC-11: pós-recuperação assistida
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('public.users')
                ->cascadeOnDelete();

            $table->foreign('account_unlocked_by_user_id')
                ->references('id')
                ->on('public.users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        Schema::dropIfExists("{$schema}.user_state");
    }
};
