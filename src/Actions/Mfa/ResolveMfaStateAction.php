<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Services\LockoutService;
use Ae3\AuthSecurity\Services\MfaRequirementResolver;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Ae3\AuthSecurity\Services\PasswordPolicyService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Monta o snapshot de estado de autenticação usado por GET /mfa/state, evitando
 * que o front precise descobrir isso reagindo a códigos de erro 403 espalhados.
 */
class ResolveMfaStateAction
{
    public function __construct(
        private readonly MfaRequirementResolver $mfaRequirementResolver,
        private readonly MfaSessionService $mfaSessionService,
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly LockoutService $lockoutService,
        private readonly ListUserFactorsAction $listUserFactors,
    ) {}

    /** @return array{must_register_factor: bool, mfa_required: bool, mfa_satisfied: bool, password_expired: bool, account_locked: bool, throttled_until: ?string, factors: Collection, contacts: array} */
    public function execute(Authenticatable $user, ?string $mfaSessionToken, ?string $context = null): array
    {
        $mfaRequired = $this->mfaRequirementResolver->isRequiredFor($user, $context);

        return [
            'must_register_factor' => method_exists($user, 'mustRegisterFactor') && $user->mustRegisterFactor(),
            'mfa_required' => $mfaRequired,
            'mfa_satisfied' => $this->isMfaSatisfied($user, $mfaSessionToken),
            'password_expired' => $this->passwordPolicyService->isExpired($user),
            'account_locked' => $this->lockoutService->isLocked($user),
            'throttled_until' => $this->lockoutService->throttledUntil($user)?->toIso8601String(),
            'factors' => $this->listUserFactors->execute($user),
            'contacts' => $user instanceof MfaContactProvider ? $user->mfaContacts() : [],
        ];
    }

    private function isMfaSatisfied(Authenticatable $user, ?string $mfaSessionToken): bool
    {
        if ($mfaSessionToken === null) {
            return false;
        }

        $sessionUserId = $this->mfaSessionService->getUserId($mfaSessionToken);

        return $sessionUserId !== null && (string) $sessionUserId === (string) $user->getAuthIdentifier();
    }
}
