<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class InvalidFactorIdentifierException extends AuthSecurityException
{
    public function __construct()
    {
        parent::__construct('O contato informado não pertence a esta conta.');
    }
}
