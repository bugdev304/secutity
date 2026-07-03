<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\AssistedRecovery;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Services\AssistedRecoveryService;
use Illuminate\Contracts\Auth\Authenticatable;

class RequestAssistedRecoveryAction
{
    public function __construct(
        private readonly AssistedRecoveryService $assistedRecoveryService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(
        int|string|null $targetUserId,
        Authenticatable $requestingUser,
        AssistedRecoveryReason $reason,
        ?string $reasonText = null,
    ): AssistedRecovery {
        $targetUser = $this->assistedRecoveryService->resolveTargetUser($targetUserId, $requestingUser);

        $recovery = $this->assistedRecoveryService->request($targetUser, $reason, $reasonText);

        $this->auditLogger->logEvent('assisted_recovery.requested', [
            'recovery_id' => $recovery->id,
            'target_user_id' => $targetUser->getAuthIdentifier(),
            'reason_category' => $reason->value,
        ]);

        return $recovery;
    }
}
