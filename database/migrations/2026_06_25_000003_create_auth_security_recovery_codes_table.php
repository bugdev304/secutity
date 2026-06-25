<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = config('auth-security.schema', 'auth_security');

        Schema::create("{$schema}.recovery_codes", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash');                       // bcrypt hash; nunca expor
            $table->uuid('generation_id');                     // identifica a leva de 8 códigos
            $table->timestamp('used_at')->nullable();
            // P2.A: null = disponível; 'used' = consumido na verificação; 'regenerated' = invalidado por nova leva
            $table->string('invalidation_reason', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('public.users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'used_at']);
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        Schema::dropIfExists("{$schema}.recovery_codes");
    }
};
