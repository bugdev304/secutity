<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('code_hash');
            $table->string('generation_id', 36);
            $table->timestamp('used_at')->nullable();
            $table->string('invalidation_reason', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'used_at']);
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_codes');
    }
};
