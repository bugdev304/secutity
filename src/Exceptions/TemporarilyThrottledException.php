<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Exceptions;

use DateTimeInterface;

/**
 * Bloqueio temporário de um estágio do backoff — diferente de AccountLockedException
 * (bloqueio definitivo, só desbloqueio administrativo), este expira só, sem ação de ninguém.
 */
class TemporarilyThrottledException extends AuthSecurityException
{
    public function __construct(
        private readonly DateTimeInterface $retryAfter,
    ) {
        parent::__construct(__('auth-security.account_throttled'));
    }

    public function getRetryAfter(): DateTimeInterface
    {
        return $this->retryAfter;
    }
}
