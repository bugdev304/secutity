<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Events;

use Ae3\AuthSecurity\Models\Factor;
use Illuminate\Foundation\Events\Dispatchable;

class MfaFactorEnrolled
{
    use Dispatchable;

    public function __construct(
        public readonly int|string $userId,
        public readonly Factor $factor,
    ) {}
}
