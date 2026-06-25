<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Events;

use Ae3\AuthSecurity\Models\AssistedRecovery;
use Illuminate\Foundation\Events\Dispatchable;

class AssistedRecoveryExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly AssistedRecovery $recovery,
        public readonly int|string $executedByUserId,
    ) {}
}
