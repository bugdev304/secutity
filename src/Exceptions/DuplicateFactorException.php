<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class DuplicateFactorException extends AuthSecurityException
{
    public function __construct()
    {
        parent::__construct('Este contato já está cadastrado como fator de verificação.');
    }
}
