<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;

class AssistedRecoveryInvalidStatusException extends AuthSecurityException
{
    public function __construct(
        AssistedRecoveryStatus $currentStatus,
        string $message = '',
    ) {
        parent::__construct(
            $message !== '' ? $message : "Cannot perform this operation when status is '{$currentStatus->value}'.",
        );
    }
}
