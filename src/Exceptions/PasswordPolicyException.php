<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class PasswordPolicyException extends AuthSecurityException
{
    /** @param list<string> $violations */
    public function __construct(
        private readonly array $violations,
        string $message = 'The password does not meet the security policy requirements.',
    ) {
        parent::__construct($message);
    }

    /** @return list<string> */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
