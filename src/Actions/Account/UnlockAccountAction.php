<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Account;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Services\LockoutService;
use Illuminate\Contracts\Auth\Authenticatable;

class UnlockAccountAction
{
    public function __construct(
        private readonly LockoutService $lockoutService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $lockedUser, Authenticatable $admin): void
    {
        $this->lockoutService->unlock($lockedUser, $admin);

        $this->auditLogger->logEvent('account.unlocked', [
            'user_id' => $lockedUser->getAuthIdentifier(),
            'unlocked_by_user_id' => $admin->getAuthIdentifier(),
        ]);
    }
}
