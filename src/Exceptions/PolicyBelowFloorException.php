<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

class PolicyBelowFloorException extends AuthSecurityException
{
    /** @param list<string> $conflicts */
    public function __construct(
        private readonly array $conflicts,
        string $message = 'The policy configuration is below the minimum floor policy.',
    ) {
        parent::__construct($message);
    }

    /** @return list<string> */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }
}
