<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;

class FakeMfaAuditLogger implements MfaAuditLogger
{
    public array $logged = [];

    public function logEvent(string $event, array $payload): void
    {
        $this->logged[] = compact('event', 'payload');
    }
}
