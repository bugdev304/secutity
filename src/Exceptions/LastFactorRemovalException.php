<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class LastFactorRemovalException extends AuthSecurityException
{
    public function __construct(string $message = 'Cannot remove the last MFA factor when MFA is required.')
    {
        parent::__construct($message);
    }
}
