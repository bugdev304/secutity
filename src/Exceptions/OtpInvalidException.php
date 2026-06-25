<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class OtpInvalidException extends AuthSecurityException
{
    public function __construct(
        string $message = 'The OTP code is invalid.',
        private readonly int $remainingAttempts = 0,
    ) {
        parent::__construct($message);
    }

    public function getRemainingAttempts(): int
    {
        return $this->remainingAttempts;
    }
}
