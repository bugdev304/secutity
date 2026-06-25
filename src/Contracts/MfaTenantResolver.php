<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MfaTenantResolver
{
    /**
     * Retorna o tenant (organização) ao qual o usuário pertence,
     * ou null se o usuário não pertence a nenhum tenant.
     */
    public function tenantOf(Authenticatable $user): ?TenantIdentity;
}
