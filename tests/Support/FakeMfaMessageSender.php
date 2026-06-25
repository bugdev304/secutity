<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaMessageSender;

class FakeMfaMessageSender implements MfaMessageSender
{
    public array $sent = [];

    public function sendOtp(string $channel, string $identifier, string $code): void
    {
        $this->sent[] = compact('channel', 'identifier', 'code');
    }
}
