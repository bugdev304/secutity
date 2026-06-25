<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\RecoveryCodeService;
use Illuminate\Contracts\Auth\Authenticatable;

class VerifyRecoveryCodeAction
{
    public function __construct(
        private readonly RecoveryCodeService $recoveryCodeService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $user, string $code): void
    {
        $recoveryCode = $this->recoveryCodeService->verify($user, $code);

        // TEC-11: pós-recuperação via código, usuário deve re-registrar fator MFA
        UserState::updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            ['must_register_factor' => true],
        );

        $this->auditLogger->logEvent('recovery_codes.used', [
            'user_id' => $user->getAuthIdentifier(),
            'recovery_code_id' => $recoveryCode->id,
        ]);
    }
}
