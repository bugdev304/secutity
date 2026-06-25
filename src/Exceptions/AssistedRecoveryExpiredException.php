<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class AssistedRecoveryExpiredException extends AuthSecurityException
{
    public function __construct(string $message = 'The assisted recovery token has expired.')
    {
        parent::__construct($message);
    }
}
