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

        Schema::create("{$tablePrefix}recovery_codes", function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash');                       // bcrypt hash; nunca expor
            $table->uuid('generation_id');                     // identifica a leva de 8 códigos
            $table->timestamp('used_at')->nullable();
            $table->string('invalidation_reason', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on($usersTable)
                ->cascadeOnDelete();

            $table->index(['user_id', 'used_at']);
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}recovery_codes");
    }
};
