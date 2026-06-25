<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class FactorNotRegisteredException extends AuthSecurityException
{
    public function __construct(string $message = 'No MFA factor is registered for this user.')
    {
        parent::__construct($message);
    }
}
