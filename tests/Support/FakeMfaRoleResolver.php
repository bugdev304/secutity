<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

class FakeMfaRoleResolver implements MfaRoleResolver
{
    public function __construct(
        private readonly array $roles = [],
        private readonly bool $requiresMfa = false,
    ) {}

    public function rolesOf(Authenticatable $user): array
    {
        return $this->roles;
    }

    public function requiresMfa(TenantIdentity $tenant, string $role, ?string $context = null): bool
    {
        return $this->requiresMfa;
    }
}
