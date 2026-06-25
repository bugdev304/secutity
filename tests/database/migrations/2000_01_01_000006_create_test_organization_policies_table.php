<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_policies', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type');
            $table->unsignedBigInteger('tenant_id');
            $table->string('role_type');
            $table->unsignedBigInteger('role_id');
            $table->string('context')->nullable();
            $table->boolean('requires_mfa');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            // SQLite não suporta partial unique index; unicidade simples cobre os testes
            $table->unique(['tenant_type', 'tenant_id', 'role_type', 'role_id', 'context']);
            $table->index(['tenant_type', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_policies');
    }
};
