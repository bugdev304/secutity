<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\TenantIdentity;

class FakeTenantIdentity implements TenantIdentity
{
    public function __construct(
        private readonly int|string $key = 1,
        private readonly string $type = 'organization',
    ) {}

    public function getTenantKey(): int|string
    {
        return $this->key;
    }

    public function getTenantType(): string
    {
        return $this->type;
    }
}
