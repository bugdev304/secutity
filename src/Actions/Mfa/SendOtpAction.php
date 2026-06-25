<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Illuminate\Contracts\Auth\Authenticatable;

class SendOtpAction
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly MfaMessageSender $messageSender,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $user, Factor $factor): void
    {
        $code = $this->otpService->generate($factor);

        $this->messageSender->sendOtp(
            $factor->type->value,
            $factor->identifier,
            $code,
        );

        $this->auditLogger->logEvent('otp.sent', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
            'channel' => $factor->type->value,
        ]);
    }
}
