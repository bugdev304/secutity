<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MfaRoleResolver
{
    /**
     * Retorna os papéis (roles) do usuário como array de strings.
     * Exemplo: ['organization_admin', 'auditor']
     */
    public function rolesOf(Authenticatable $user): array;

    /**
     * Indica se determinado papel exige MFA naquele tenant e contexto de acesso.
     * Consulta a política do pacote (organization_policies + floor).
     */
    public function requiresMfa(TenantIdentity $tenant, string $role, ?string $context = null): bool;
}
