<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Concerns;

use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Models\PasswordHistory;
use Ae3\AuthSecurity\Models\RecoveryCode;
use Ae3\AuthSecurity\Models\UserState;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Adicione ao User model da aplicação hospedeira:
 *   use Ae3\AuthSecurity\Concerns\HasAuthSecurity;
 */
trait HasAuthSecurity
{
    public function authSecurityState(): HasOne
    {
        return $this->hasOne(UserState::class, 'user_id');
    }

    public function mfaFactors(): HasMany
    {
        return $this->hasMany(Factor::class, 'user_id');
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(RecoveryCode::class, 'user_id');
    }

    public function passwordHistory(): HasMany
    {
        return $this->hasMany(PasswordHistory::class, 'user_id');
    }

    public function assistedRecoveries(): HasMany
    {
        return $this->hasMany(AssistedRecovery::class, 'target_user_id');
    }

    public function isAccountLocked(): bool
    {
        return (bool) $this->authSecurityState?->isLocked();
    }

    public function mustRegisterFactor(): bool
    {
        return (bool) $this->authSecurityState?->must_register_factor;
    }

    public function hasMfaFactor(): bool
    {
        return $this->mfaFactors()->confirmed()->exists();
    }

    public function hasAvailableRecoveryCodes(): bool
    {
        return $this->recoveryCodes()->available()->exists();
    }
}
