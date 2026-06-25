<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MfaSessionService
{
    private const SESSION_TTL_MINUTES = 480; // 8h

    public function create(Authenticatable $user): array
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(self::SESSION_TTL_MINUTES);

        Cache::store(config('auth-security.cache.driver'))->put(
            $this->cacheKey($token),
            $user->getAuthIdentifier(),
            $expiresAt,
        );

        return [
            'mfa_session_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function getUserId(string $token): int|string|null
    {
        return Cache::store(config('auth-security.cache.driver'))->get($this->cacheKey($token));
    }

    public function invalidate(string $token): void
    {
        Cache::store(config('auth-security.cache.driver'))->forget($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        $prefix = config('auth-security.cache.key_prefix', 'auth_security:');

        return "{$prefix}mfa_session:{$token}";
    }
}
