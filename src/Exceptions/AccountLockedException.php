<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

use DateTimeInterface;

class AccountLockedException extends AuthSecurityException
{
    public function __construct(
        string $message = 'This account has been locked due to too many failed attempts.',
        private readonly ?DateTimeInterface $lockedAt = null,
    ) {
        parent::__construct(__('auth-security.account_locked') ?? $message);
    }

    public function getLockedAt(): ?DateTimeInterface
    {
        return $this->lockedAt;
    }
}
