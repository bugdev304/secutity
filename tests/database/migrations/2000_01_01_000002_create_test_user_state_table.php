<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_state', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('account_locked_at')->nullable();
            $table->unsignedBigInteger('account_unlocked_by_user_id')->nullable();
            $table->timestamp('account_unlocked_at')->nullable();
            $table->boolean('must_register_factor')->default(false);
            $table->timestamp('recovery_refused_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_state');
    }
};
