<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Verifica o código de um fator (OTP ou TOTP) já escopado pelo usuário autenticado
 * e abre a sessão MFA. Consolida a resolução do fator + o dispatch pelo tipo, que
 * antes viviam no controller.
 */
class VerifyMfaFactorAction
{
    public function __construct(
        private readonly VerifyOtpAction $verifyOtp,
        private readonly VerifyTotpAction $verifyTotp,
        private readonly MfaSessionService $mfaSessionService,
    ) {}

    public function execute(Authenticatable $user, int $factorId, FactorType $factorType, string $code): array
    {
        $factor = Factor::where('user_id', $user->getAuthIdentifier())->findOrFail($factorId);

        match ($factorType) {
            FactorType::EMAIL, FactorType::SMS => $this->verifyOtp->execute($user, $factor, $code),
            FactorType::AUTHENTICATOR_APP => $this->verifyTotp->execute($user, $factor, $code),
        };

        return $this->mfaSessionService->create($user);
    }
}
