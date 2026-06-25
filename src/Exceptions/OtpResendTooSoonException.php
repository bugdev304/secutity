<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class OtpResendTooSoonException extends AuthSecurityException
{
    public function __construct(
        private readonly int $secondsRemaining,
        string $message = 'Please wait before requesting a new OTP.',
    ) {
        parent::__construct($message);
    }

    public function getSecondsRemaining(): int
    {
        return $this->secondsRemaining;
    }
}
