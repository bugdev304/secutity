<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;

class VerifyTotpAction
{
    public function __construct(
        private readonly TotpService $totpService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /** Usado tanto na confirmação de cadastro quanto no fluxo de login. */
    public function execute(Authenticatable $user, Factor $factor, string $code): void
    {
        $this->totpService->verify($factor, $code);

        $factor->update(['last_used_at' => now()]);

        $this->auditLogger->logEvent('totp.verified', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
        ]);
    }
}
