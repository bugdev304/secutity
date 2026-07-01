<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\AssistedRecovery;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Services\AssistedRecoveryService;
use Illuminate\Contracts\Auth\Authenticatable;

class RefuseAssistedRecoveryAction
{
    public function __construct(
        private readonly AssistedRecoveryService $assistedRecoveryService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(AssistedRecovery $recovery, Authenticatable $admin, ?string $refusedReasonText = null): void
    {
        $this->assistedRecoveryService->refuse($recovery, $admin, $refusedReasonText);

        $this->auditLogger->logEvent('assisted_recovery.refused', [
            'recovery_id' => $recovery->id,
            'target_user_id' => $recovery->target_user_id,
            'executed_by_user_id' => $admin->getAuthIdentifier(),
        ]);
    }
}
