<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Defaults;

use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Implementação padrão do MfaTenantResolver: todos os usuários são sem tenant.
 * Consequência: remoção de fator não usa política de tenant (mfaRequired = false).
 * Configure tenant_resolver em config/auth-security.php para apps multi-tenant.
 */
class NullMfaTenantResolver implements MfaTenantResolver
{
    public function tenantOf(Authenticatable $user): ?TenantIdentity
    {
        return null;
    }
}
