<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

class FakeMfaTenantResolver implements MfaTenantResolver
{
    public function __construct(
        private readonly ?TenantIdentity $tenant = null,
    ) {}

    public function tenantOf(Authenticatable $user): ?TenantIdentity
    {
        return $this->tenant;
    }
}
