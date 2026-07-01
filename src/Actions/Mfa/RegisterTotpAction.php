<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Data\TotpRegistrationData;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\TotpService;
use Illuminate\Contracts\Auth\Authenticatable;

class RegisterTotpAction
{
    public function __construct(
        private readonly TotpService $totpService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /**
     * Cria fator TOTP e retorna dados para exibição ao usuário (seed + QR code).
     * O usuário deve confirmar via VerifyTotpAction antes do fator ser considerado ativo.
     *
     * @param  string  $holderName  Identificador exibido no app autenticador (ex.: e-mail do usuário)
     * @param  string|null  $factorName  Rótulo para o fator (ex.: "Aplicativo pessoal")
     */
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
        ]);

        $this->auditLogger->logEvent('totp.registered', [
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
