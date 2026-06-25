<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Exceptions\AccountLockedException;
use Ae3\AuthSecurity\Models\UserState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class LockoutService
{
    public function isLocked(Authenticatable $user): bool
    {
        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();

        return $state !== null && $state->isLocked();
    }

    /**
     * Registra uma tentativa de login falha.
     * Lança AccountLockedException se a conta já estava bloqueada ou se o limiar
     * foi atingido com esta tentativa, bloqueando a conta imediatamente.
     */
    public function recordFailedAttempt(Authenticatable $user): void
    {
        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();
        if ($state !== null && $state->isLocked()) {
            throw new AccountLockedException(lockedAt: $state->account_locked_at);
        }

        $cacheDriver = config('auth-security.cache.driver');
        $maxAttempts = config('auth-security.lockout.max_attempts', 5);
        $windowMinutes = config('auth-security.lockout.window_minutes', 10);
        $attemptsKey = $this->attemptsCacheKey($user);

        if (! Cache::store($cacheDriver)->has($attemptsKey)) {
            Cache::store($cacheDriver)->put($attemptsKey, 1, now()->addMinutes($windowMinutes));
            $currentCount = 1;
        } else {
            $currentCount = Cache::store($cacheDriver)->increment($attemptsKey);
        }

        if ($currentCount >= $maxAttempts) {
            $lockedState = $this->lock($user);
            throw new AccountLockedException(lockedAt: $lockedState->account_locked_at);
        }
    }

    /** Bloqueia a conta imediatamente, independente do contador de tentativas. */
    public function lock(Authenticatable $user): UserState
    {
        return UserState::updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            ['account_locked_at' => now()],
        );
    }

    /** Desbloqueia a conta e registra quem realizou a ação. */
    public function unlock(Authenticatable $user, ?Authenticatable $unlockedBy = null): void
    {
        UserState::updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'account_locked_at' => null,
                'account_unlocked_by_user_id' => $unlockedBy?->getAuthIdentifier(),
                'account_unlocked_at' => now(),
            ],
        );

        $this->resetAttempts($user);
    }

    /** Zera o contador de tentativas falhas no cache (chamar após login bem-sucedido). */
    public function resetAttempts(Authenticatable $user): void
    {
        Cache::store(config('auth-security.cache.driver'))
            ->forget($this->attemptsCacheKey($user));
    }

    private function attemptsCacheKey(Authenticatable $user): string
    {
        $prefix = config('auth-security.cache.key_prefix', 'auth_security:');

        return "{$prefix}lockout_attempts:{$user->getAuthIdentifier()}";
    }
}
