<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class OtpExpiredException extends AuthSecurityException
{
    public function __construct(string $message = 'The OTP code has expired.')
    {
        parent::__construct($message);
    }
}
