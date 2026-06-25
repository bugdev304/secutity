<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class AssistedRecoveryInvalidTokenException extends AuthSecurityException
{
    public function __construct(string $message = 'The assisted recovery token is invalid.')
    {
        parent::__construct($message);
    }
}
