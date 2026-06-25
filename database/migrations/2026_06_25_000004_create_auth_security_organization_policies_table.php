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
        $qualifiedTable = "{$schema}.organization_policies";

        Schema::create($qualifiedTable, function (Blueprint $table) {
            $table->id();
            $table->string('tenant_type');         // classe do tenant (ex.: App\Models\Company)
            $table->unsignedBigInteger('tenant_id');
            $table->string('role_type');           // identificador do papel
            $table->unsignedBigInteger('role_id');
            $table->string('context')->nullable(); // ex.: 'web_admin', 'citizen'; null = qualquer contexto
            $table->boolean('requires_mfa');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });

        // Unicidade com context nullable: duas partial unique indexes garantem semântica correta no PostgreSQL.
        // NULLS em unique index padrão são tratados como distintos (NULL != NULL), permitindo duplicatas indesejadas.
        DB::statement(
            "CREATE UNIQUE INDEX org_policies_non_null_ctx_unique
             ON \"{$schema}\".organization_policies (tenant_type, tenant_id, role_type, role_id, context)
             WHERE context IS NOT NULL"
        );

        DB::statement(
            "CREATE UNIQUE INDEX org_policies_null_ctx_unique
             ON \"{$schema}\".organization_policies (tenant_type, tenant_id, role_type, role_id)
             WHERE context IS NULL"
        );
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        Schema::dropIfExists("{$schema}.organization_policies");
    }
};
