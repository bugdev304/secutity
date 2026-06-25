<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Illuminate\Contracts\Auth\Authenticatable;

class VerifyOtpAction
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $user, Factor $factor, string $code): void
    {
        $this->otpService->verify($factor, $code);

        $factor->update(['last_used_at' => now()]);

        $this->auditLogger->logEvent('otp.verified', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
            'channel' => $factor->type->value,
        ]);
    }
}
