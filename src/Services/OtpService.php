<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Exceptions\OtpExpiredException;
use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Exceptions\OtpResendLimitException;
use Ae3\AuthSecurity\Exceptions\OtpResendTooSoonException;
use Ae3\AuthSecurity\Models\Factor;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function generate(Factor $factor): string
    {
        $cacheDriver = config('auth-security.cache.driver');
        $hasActiveOtp = Cache::store($cacheDriver)->has($this->otpCacheKey($factor));

        if ($hasActiveOtp) {
            $this->enforceResendConstraints($factor, $cacheDriver);
        }

        $code = $this->generateNumericCode();
        $expiresAt = now()->addMinutes(config('auth-security.mfa.otp_validity_minutes'));

        Cache::store($cacheDriver)->put($this->otpCacheKey($factor), Hash::make($code), $expiresAt);
        $this->storeMeta($factor, $cacheDriver, $hasActiveOtp, $expiresAt);

        return $code;
    }

    public function verify(Factor $factor, string $code): void
    {
        $cacheDriver = config('auth-security.cache.driver');
        $otpKey = $this->otpCacheKey($factor);
        $storedHash = Cache::store($cacheDriver)->get($otpKey);

        if ($storedHash === null) {
            throw new OtpExpiredException;
        }

        if (! Hash::check($code, $storedHash)) {
            $remainingAttempts = $this->registerFailedAttempt($factor, $cacheDriver);

            if ($remainingAttempts <= 0) {
                Cache::store($cacheDriver)->forget($otpKey);
                Cache::store($cacheDriver)->forget($this->metaCacheKey($factor));
            }

            throw new OtpInvalidException(remainingAttempts: $remainingAttempts);
        }

        // Invalidação imediata: OTP é de uso único
        Cache::store($cacheDriver)->forget($otpKey);
        Cache::store($cacheDriver)->forget($this->metaCacheKey($factor));
    }

    public function canResend(Factor $factor): bool
    {
        $cacheDriver = config('auth-security.cache.driver');

        if (! Cache::store($cacheDriver)->has($this->otpCacheKey($factor))) {
            return true;
        }

        $meta = $this->readMeta($factor, $cacheDriver);

        if ($meta['resend_count'] >= config('auth-security.mfa.otp_resend_limit')) {
            return false;
        }

        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds');

        return (now()->timestamp - $meta['last_sent_at']) >= $intervalSeconds;
    }

    private function enforceResendConstraints(Factor $factor, ?string $cacheDriver): void
    {
        $meta = $this->readMeta($factor, $cacheDriver);

        if ($meta['resend_count'] >= config('auth-security.mfa.otp_resend_limit')) {
            throw new OtpResendLimitException;
        }

        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds');
        $secondsElapsed = now()->timestamp - $meta['last_sent_at'];

        if ($secondsElapsed < $intervalSeconds) {
            throw new OtpResendTooSoonException($intervalSeconds - $secondsElapsed);
        }
    }

    /** Incrementa o contador de tentativas falhas e retorna quantas restam. */
    private function registerFailedAttempt(Factor $factor, ?string $cacheDriver): int
    {
        $meta = $this->readMeta($factor, $cacheDriver);
        $meta['attempts']++;

        $validityMinutes = config('auth-security.mfa.otp_validity_minutes');
        Cache::store($cacheDriver)->put($this->metaCacheKey($factor), $meta, now()->addMinutes($validityMinutes));

        $maxAttempts = config('auth-security.mfa.otp_max_attempts');

        return max(0, $maxAttempts - $meta['attempts']);
    }

    private function storeMeta(Factor $factor, ?string $cacheDriver, bool $isResend, DateTimeInterface $expiresAt): void
    {
        $meta = $this->readMeta($factor, $cacheDriver);

        if ($isResend) {
            $meta['resend_count']++;
        }

        $meta['last_sent_at'] = now()->timestamp;

        Cache::store($cacheDriver)->put($this->metaCacheKey($factor), $meta, $expiresAt);
    }

    private function readMeta(Factor $factor, ?string $cacheDriver): array
    {
        return Cache::store($cacheDriver)->get(
            $this->metaCacheKey($factor),
            ['resend_count' => 0, 'last_sent_at' => 0, 'attempts' => 0],
        );
    }

    private function generateNumericCode(): string
    {
        $length = config('auth-security.mfa.otp_length');
        $max = (int) str_repeat('9', $length);

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    private function otpCacheKey(Factor $factor): string
    {
        $prefix = config('auth-security.cache.key_prefix');

        return "{$prefix}otp:{$factor->user_id}:{$factor->id}";
    }

    private function metaCacheKey(Factor $factor): string
    {
        $prefix = config('auth-security.cache.key_prefix');

        return "{$prefix}otp_meta:{$factor->user_id}:{$factor->id}";
    }
}
