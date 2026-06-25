<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Services\RecoveryCodeService;
use Illuminate\Contracts\Auth\Authenticatable;

class GenerateRecoveryCodesAction
{
    public function __construct(
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /** @return string[] Códigos em plain text — exibir ao usuário e nunca reter */
    public function execute(Authenticatable $user): array
    {
        $plainCodes = $this->recoveryCodeService->generate($user);

        $this->auditLogger->logEvent('recovery_codes.generated', [
            'user_id' => $user->getAuthIdentifier(),
            'count' => count($plainCodes),
        ]);

        return $plainCodes;
    }
}
