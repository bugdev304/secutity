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

        Schema::create("{$tablePrefix}password_history", function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on($usersTable)
                ->cascadeOnDelete();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}password_history");
    }
};
