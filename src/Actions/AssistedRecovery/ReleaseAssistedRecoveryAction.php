<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\AssistedRecovery;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Services\AssistedRecoveryService;
use Illuminate\Contracts\Auth\Authenticatable;

class ReleaseAssistedRecoveryAction
{
    public function __construct(
        private readonly AssistedRecoveryService $assistedRecoveryService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /** Retorna o token plain text que deve ser entregue ao usuário-alvo por canal seguro. */
    public function execute(AssistedRecovery $recovery, Authenticatable $admin): string
    {
        $plainToken = $this->assistedRecoveryService->release($recovery, $admin);

        $this->auditLogger->logEvent('assisted_recovery.released', [
            'recovery_id' => $recovery->id,
            'target_user_id' => $recovery->target_user_id,
            'executed_by_user_id' => $admin->getAuthIdentifier(),
        ]);

        return $plainToken;
    }
}
