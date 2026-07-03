<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\OtpService;
use Ae3\AuthSecurity\Services\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Confirma o cadastro de um fator verificando o código de confirmação.
 * Para OTP (email/sms): valida via OtpService.
 * Para TOTP: valida via TotpService.
 * Em caso de sucesso, define confirmed_at e o fator passa a ser ativo.
 */
class ConfirmFactorEnrollmentAction
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly TotpService $totpService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $user, Factor $factor, string $code): Factor
    {
        if ($factor->type->isOtp()) {
            $this->otpService->verify($factor, $code);
        } else {
            $this->totpService->verify($factor, $code);
        }

        DB::transaction(function () use ($user, $factor) {
            $factor->update(['confirmed_at' => now()]);

            UserState::updateOrCreate(
                ['user_id' => $user->getAuthIdentifier()],
                ['must_register_factor' => false],
            );
        });

        $this->auditLogger->logEvent('mfa.factor.enrolled', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
            'factor_type' => $factor->type->value,
        ]);

        return $factor->fresh();
    }
}
