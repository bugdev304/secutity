<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Enums\MfaChannel;
use Ae3\AuthSecurity\Exceptions\DuplicateFactorException;
use Ae3\AuthSecurity\Exceptions\InvalidFactorIdentifierException;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Inicia o cadastro de um fator OTP (email ou sms).
 * Cria o factor com confirmed_at=null e envia OTP de confirmação.
 * Após confirmação do OTP via ConfirmFactorEnrollmentAction, o fator fica ativo.
 */
class EnrollOtpFactorAction
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly MfaMessageSender $messageSender,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(
        Authenticatable $user,
        FactorType $factorType,
        string $identifier,
        ?string $name = null,
    ): Factor {
        // Se o User declara seus contatos, o identifier deve ser um deles.
        // Impede que o usuário cadastre contatos de terceiros e receba OTPs em nome deles.
        if ($user instanceof MfaContactProvider) {
            $allowedIdentifiers = array_map(
                fn (MfaContact $contact) => $contact->identifier,
                $user->mfaContacts(),
            );

            if (! in_array($identifier, $allowedIdentifiers, strict: true)) {
                throw new InvalidFactorIdentifierException;
            }
        }

        $alreadyEnrolled = Factor::where('user_id', $user->getAuthIdentifier())
            ->where('type', $factorType)
            ->where('identifier', $identifier)
            ->exists();

        if ($alreadyEnrolled) {
            throw new DuplicateFactorException;
        }

        $factor = Factor::create([
            'user_id' => $user->getAuthIdentifier(),
            'type' => $factorType,
            'identifier' => $identifier,
            'name' => $name,
            // confirmed_at permanece null até confirmação
        ]);

        $code = $this->otpService->generate($factor);

        $this->messageSender->sendOtp(MfaChannel::from($factorType->value), $identifier, $code);

        $this->auditLogger->logEvent('mfa.factor.enrollment_started', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factor->id,
            'factor_type' => $factorType->value,
        ]);

        return $factor;
    }
}
