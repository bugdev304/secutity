<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Defaults;

use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Implementação padrão do MfaRoleResolver: sem papéis, MFA nunca obrigatório via policy.
 * Consequência: remoção de fator é sempre permitida (a menos que o piso floor_policy cubra).
 * Configure role_resolver em config/auth-security.php para apps com RBAC.
 */
class NullMfaRoleResolver implements MfaRoleResolver
{
    public function rolesOf(Authenticatable $user): array
    {
        return [];
    }

    public function requiresMfa(TenantIdentity $tenant, string $role, ?string $context = null): bool
    {
        return false;
    }
}
