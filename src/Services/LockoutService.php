<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Exceptions\AccountLockedException;
use Ae3\AuthSecurity\Exceptions\TemporarilyThrottledException;
use Ae3\AuthSecurity\Models\UserState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class LockoutService
{
    public function isLocked(Authenticatable $user): bool
    {
        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();

        return $state !== null && $state->isLocked();
    }

    /** Até quando a conta está temporariamente bloqueada (estágio de backoff), ou null se não estiver. */
    public function throttledUntil(Authenticatable $user): ?Carbon
    {
        $value = Cache::store(config('auth-security.cache.driver'))->get($this->throttleCacheKey($user));

        if ($value === null) {
            return null;
        }

        $retryAfter = Carbon::parse($value);

        return $retryAfter->isFuture() ? $retryAfter : null;
    }

    /**
     * Registra uma tentativa de login falha.
     * Lança AccountLockedException se a conta já estava bloqueada definitivamente.
     * Lança TemporarilyThrottledException se ainda dentro de um bloqueio de estágio,
     * ou se esta tentativa acabou de atingir o limiar de um novo estágio.
     * A cada `attempts_per_stage` falhas, avança um estágio em `backoff_minutes`;
     * ao esgotar o array, bloqueia definitivamente.
     */
    public function recordFailedAttempt(Authenticatable $user): void
    {
        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();
        if ($state !== null && $state->isLocked()) {
            throw new AccountLockedException(lockedAt: $state->account_locked_at);
        }

        $throttledUntil = $this->throttledUntil($user);
        if ($throttledUntil !== null) {
            throw new TemporarilyThrottledException(retryAfter: $throttledUntil);
        }

        $cacheDriver = config('auth-security.cache.driver');
        $attemptsPerStage = (int) config('auth-security.lockout.attempts_per_stage');
        $backoffMinutes = config('auth-security.lockout.backoff_minutes');
        $resetAfterMinutes = (int) config('auth-security.lockout.reset_after_minutes');
        $attemptsKey = $this->attemptsCacheKey($user);

        $currentCount = ((int) Cache::store($cacheDriver)->get($attemptsKey, 0)) + 1;
        Cache::store($cacheDriver)->put($attemptsKey, $currentCount, now()->addMinutes($resetAfterMinutes));

        if ($currentCount % $attemptsPerStage !== 0) {
            return;
        }

        $stageIndex = (int) ($currentCount / $attemptsPerStage) - 1;

        if ($stageIndex >= count($backoffMinutes)) {
            $lockedState = $this->lock($user);
            throw new AccountLockedException(lockedAt: $lockedState->account_locked_at);
        }

        $retryAfter = now()->addMinutes($backoffMinutes[$stageIndex]);
        Cache::store($cacheDriver)->put($this->throttleCacheKey($user), $retryAfter->toIso8601String(), $retryAfter);

        throw new TemporarilyThrottledException(retryAfter: $retryAfter);
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

    /** Zera o contador de tentativas falhas e qualquer bloqueio de estágio ativo (chamar após login bem-sucedido). */
    public function resetAttempts(Authenticatable $user): void
    {
        $cacheDriver = config('auth-security.cache.driver');

        Cache::store($cacheDriver)->forget($this->attemptsCacheKey($user));
        Cache::store($cacheDriver)->forget($this->throttleCacheKey($user));
    }

    private function throttleCacheKey(Authenticatable $user): string
    {
        $prefix = config('auth-security.cache.key_prefix');

        return "{$prefix}lockout_throttle:{$user->getAuthIdentifier()}";
    }

    private function attemptsCacheKey(Authenticatable $user): string
    {
        $prefix = config('auth-security.cache.key_prefix');

        return "{$prefix}lockout_attempts:{$user->getAuthIdentifier()}";
    }
}
