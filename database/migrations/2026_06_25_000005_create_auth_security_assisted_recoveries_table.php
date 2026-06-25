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

        Schema::create("{$tablePrefix}assisted_recoveries", function (Blueprint $table) use ($usersTable): void {
            $table->id();
            $table->unsignedBigInteger('target_user_id');
            $table->unsignedBigInteger('executed_by_user_id')->nullable();
            $table->string('reason_category', 32);
            $table->text('reason_text')->nullable();
            $table->string('status', 32);
            $table->string('recovery_token_hash')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->timestamps();

            $table->foreign('target_user_id')
                ->references('id')
                ->on($usersTable)
                ->restrictOnDelete();

            $table->foreign('executed_by_user_id')
                ->references('id')
                ->on($usersTable)
                ->restrictOnDelete();

            $table->index('target_user_id');
            $table->index('status');
            $table->index('recovery_token_hash');
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}assisted_recoveries");
    }
};
