<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\AssistedRecovery;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Services\AssistedRecoveryService;

class CompleteAssistedRecoveryAction
{
    public function __construct(
        private readonly AssistedRecoveryService $assistedRecoveryService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(AssistedRecovery $recovery, string $plainToken): void
    {
        $this->assistedRecoveryService->complete($recovery, $plainToken);

        $this->auditLogger->logEvent('assisted_recovery.completed', [
            'recovery_id' => $recovery->id,
            'target_user_id' => $recovery->target_user_id,
        ]);
    }
}
