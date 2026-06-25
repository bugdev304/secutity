<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Account;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Services\LockoutService;
use Illuminate\Contracts\Auth\Authenticatable;

class RecordFailedLoginAction
{
    public function __construct(
        private readonly LockoutService $lockoutService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /**
     * Registra tentativa falha e bloqueia a conta ao atingir o limiar.
     * Lança AccountLockedException se já bloqueada ou se foi bloqueada agora.
     */
    public function execute(Authenticatable $user): void
    {
        $this->lockoutService->recordFailedAttempt($user);

        $this->auditLogger->logEvent('login.failed', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
    }
}
