<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Password;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\PasswordPolicyService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    public function __construct(
        private readonly PasswordPolicyService $passwordPolicyService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /**
     * O campo de senha do model do host deve ser configurado SEM o cast 'hashed',
     * pois o hash é feito aqui explicitamente para que possamos gravá-lo no histórico.
     */
    public function execute(Authenticatable $user, string $newPassword): UserState
    {
        $this->passwordPolicyService->validate($user, $newPassword);

        $hashedPassword = Hash::make($newPassword);

        $user->forceFill(['password' => $hashedPassword])->save();

        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $this->passwordPolicyService->record($user, $hashedPassword);

        $userState = UserState::updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            ['password_changed_at' => now()],
        );

        $this->auditLogger->logEvent('password.changed', [
            'user_id' => $user->getAuthIdentifier(),
        ]);

        return $userState;
    }
}
