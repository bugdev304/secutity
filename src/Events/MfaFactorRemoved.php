<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MfaFactorRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $userId,
        public readonly int|string $factorId,
        public readonly string $factorType,
    ) {}
}
