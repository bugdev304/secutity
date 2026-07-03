<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assisted_recoveries', function (Blueprint $table) {
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
            $table->text('refused_reason_text')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assisted_recoveries');
    }
};
