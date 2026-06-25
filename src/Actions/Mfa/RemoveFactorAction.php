<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Exceptions\LastFactorRemovalException;
use Ae3\AuthSecurity\Models\Factor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Remove um fator MFA do usuário.
 * Lança LastFactorRemovalException se for o último fator confirmado
 * e a política exige ao menos um fator (requires_mfa=true via config floor_policy).
 */
class RemoveFactorAction
{
    public function __construct(
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function execute(Authenticatable $user, Factor $factor, bool $mfaRequired = false): void
    {
        if ($mfaRequired) {
            $remainingConfirmed = Factor::where('user_id', $user->getAuthIdentifier())
                ->confirmed()
                ->where('id', '!=', $factor->id)
                ->count();

            if ($remainingConfirmed === 0) {
                throw new LastFactorRemovalException;
            }
        }

        $factorId = $factor->id;
        $factorType = $factor->type->value;

        $factor->delete();

        $this->auditLogger->logEvent('mfa.factor.removed', [
            'user_id' => $user->getAuthIdentifier(),
            'factor_id' => $factorId,
            'factor_type' => $factorType,
        ]);
    }
}
