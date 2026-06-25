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

        // Nome qualificado para as partial unique indexes (sem aspas duplas no SQLite)
        $qualifiedTable = ($isPgsql && $schema)
            ? "\"{$schema}\".organization_policies"
            : 'organization_policies';

        Schema::create("{$tablePrefix}organization_policies", function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_type');
            $table->unsignedBigInteger('tenant_id');
            $table->string('role_type');
            $table->unsignedBigInteger('role_id');
            $table->string('context')->nullable();
            $table->boolean('requires_mfa');
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['tenant_type', 'tenant_id']);
        });

        // Duas partial unique indexes garantem unicidade mesmo com context nullable.
        // SQLite também suporta partial indexes (WHERE clause).
        DB::statement(
            "CREATE UNIQUE INDEX org_policies_non_null_ctx_unique
             ON {$qualifiedTable} (tenant_type, tenant_id, role_type, role_id, context)
             WHERE context IS NOT NULL"
        );

        DB::statement(
            "CREATE UNIQUE INDEX org_policies_null_ctx_unique
             ON {$qualifiedTable} (tenant_type, tenant_id, role_type, role_id)
             WHERE context IS NULL"
        );
    }

    public function down(): void
    {
        $schema = config('auth-security.schema', 'auth_security');
        $isPgsql = DB::getDriverName() === 'pgsql';
        $tablePrefix = ($isPgsql && $schema) ? "{$schema}." : '';

        Schema::dropIfExists("{$tablePrefix}organization_policies");
    }
};
