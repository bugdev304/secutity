<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class OtpResendLimitException extends AuthSecurityException
{
    public function __construct(string $message = 'The maximum number of OTP resends has been reached.')
    {
        parent::__construct($message);
    }
}
