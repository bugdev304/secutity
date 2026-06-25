<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class MfaAlreadyVerifiedException extends AuthSecurityException
{
    public function __construct(string $message = 'MFA has already been verified for this session.')
    {
        parent::__construct($message);
    }
}
