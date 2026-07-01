<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Data\TotpRegistrationData;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Inicia o cadastro de um fator TOTP (aplicativo verificador).
 * Cria o factor com confirmed_at=null e retorna o seed + QR code.
 * Após confirmar o primeiro código via ConfirmFactorEnrollmentAction, o fator fica ativo.
 */
class EnrollTotpFactorAction
{
    public function __construct(
        private readonly TotpService $totpService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(
        Authenticatable $user,
        string $holderName,
        ?string $factorName = null,
    ): TotpRegistrationData {
        $plainSecret = $this->totpService->generateSecret();

        $factor = Factor::create([
            'user_id' => $user->getAuthIdentifier(),
            'type' => FactorType::AUTHENTICATOR_APP,
            'secret_encrypted' => $plainSecret,
            'name' => $factorName,
            // confirmed_at permanece null até confirmação com primeiro código
        ]);

        $this->auditLogger->logEvent('mfa.factor.totp_enrollment_started', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
        ]);

        return new TotpRegistrationData(
            factorId: $factor->id,
            plainSecret: $plainSecret,
            qrCodeUri: $this->totpService->getQrCodeUri($factor, $holderName),
            qrCodeInline: $this->totpService->getQrCodeInline($factor, $holderName),
        );
    }
}
