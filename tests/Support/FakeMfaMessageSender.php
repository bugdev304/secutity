<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Enums\MfaChannel;

class FakeMfaMessageSender implements MfaMessageSender
{
    public array $sent = [];

    public function sendOtp(MfaChannel $channel, string $identifier, string $code): void
    {
        $this->sent[] = compact('channel', 'identifier', 'code');
    }
}
