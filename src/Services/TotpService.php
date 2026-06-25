<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Models\Factor;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FAQRCode\Google2FA;

class TotpService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /** Gera seed base32 de 32 caracteres para TOTP. */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** Retorna a URI otpauth:// para que apps como Google Authenticator importem via QR. */
    public function getQrCodeUri(Factor $factor, string $holderName): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('auth-security.mfa.totp_issuer', config('app.name', 'App')),
            $holderName,
            $factor->secret_encrypted, // cast 'encrypted' devolve a seed em plain text
        );
    }

    /** Retorna data URL com QR code SVG inline, pronto para exibição no browser. */
    public function getQrCodeInline(Factor $factor, string $holderName, int $size = 200): string
    {
        return $this->google2fa->getQRCodeInline(
            config('auth-security.mfa.totp_issuer', config('app.name', 'App')),
            $holderName,
            $factor->secret_encrypted,
            $size,
        );
    }

    /**
     * Verifica código TOTP e previne replay armazenando o timestamp do último uso.
     * Lança OtpInvalidException se o código for inválido ou já tiver sido usado.
     */
    public function verify(Factor $factor, string $code): void
    {
        $cacheDriver = config('auth-security.cache.driver');
        $timestampKey = $this->timestampCacheKey($factor);
        // Default 0 (em vez de null) para que verifyKeyNewer sempre retorne o timestamp int,
        // necessário para guardar o período real verificado e bloquear replay corretamente.
        $oldTimestamp = Cache::store($cacheDriver)->get($timestampKey, 0);

        // verifyKeyNewer retorna o timestamp do período válido (int) ou false
        $newTimestamp = $this->google2fa->verifyKeyNewer(
            $factor->secret_encrypted,
            $code,
            $oldTimestamp,
        );

        if ($newTimestamp === false) {
            throw new OtpInvalidException;
        }

        // Guarda o timestamp para bloquear replay do mesmo período
        Cache::store($cacheDriver)->put(
            $timestampKey,
            $newTimestamp,
            now()->addMinutes(5),
        );
    }

    private function timestampCacheKey(Factor $factor): string
    {
        $prefix = config('auth-security.cache.key_prefix', 'auth_security:');

        return "{$prefix}totp_ts:{$factor->user_id}:{$factor->id}";
    }
}
