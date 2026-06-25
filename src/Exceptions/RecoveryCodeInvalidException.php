<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class RecoveryCodeInvalidException extends AuthSecurityException
{
    public function __construct(string $message = 'The recovery code is invalid or has already been used.')
    {
        parent::__construct($message);
    }
}
