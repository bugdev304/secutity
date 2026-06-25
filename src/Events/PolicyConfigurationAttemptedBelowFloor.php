<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Events;

use Illuminate\Foundation\Events\Dispatchable;

class PolicyConfigurationAttemptedBelowFloor
{
    use Dispatchable;

    /** @param list<string> $conflicts */
    public function __construct(
        public readonly int|string $userId,
        public readonly string $tenantType,
        public readonly int|string $tenantId,
        public readonly array $conflicts,
    ) {}
}
