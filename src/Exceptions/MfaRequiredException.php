<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class MfaRequiredException extends AuthSecurityException
{
    public function __construct(string $message = 'MFA verification is required to proceed.')
    {
        parent::__construct($message);
    }
}
