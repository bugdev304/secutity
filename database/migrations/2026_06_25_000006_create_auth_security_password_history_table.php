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

        Schema::create("{$schema}.password_history", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('password_hash');       // bcrypt; verificar com Hash::check
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                ->references('id')
                ->on('public.users')
                ->cascadeOnDelete();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        Schema::dropIfExists("{$schema}.password_history");
    }
};
