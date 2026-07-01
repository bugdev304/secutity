<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\TotpService;
use Ae3\AuthSecurity\Tests\TestCase;
use PragmaRX\Google2FAQRCode\Google2FA;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totpService = new TotpService;
        $this->google2fa = new Google2FA;
    }

    public function test_generate_secret_returns_valid_base32_string(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
    }

    public function test_generate_secret_is_unique_each_call(): void
    {
        $secret1 = $this->totpService->generateSecret();
        $secret2 = $this->totpService->generateSecret();

        $this->assertNotEquals($secret1, $secret2);
    }

    public function test_get_qr_code_uri_returns_otpauth_uri(): void
    {
        $factor = $this->makeFactorWithSecret();

        $uri = $this->totpService->getQrCodeUri($factor, 'user@example.com');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('user%40example.com', $uri);
    }

    public function test_get_qr_code_inline_returns_data_uri(): void
    {
        $factor = $this->makeFactorWithSecret();

        $inline = $this->totpService->getQrCodeInline($factor, 'user@example.com');

        $this->assertStringStartsWith('data:', $inline);
    }

    public function test_verify_succeeds_with_current_totp_code(): void
    {
        $factor = $this->makeFactorWithSecret();
        $currentCode = $this->google2fa->getCurrentOtp($factor->secret_encrypted);

        // Sem exceção = sucesso
        $this->totpService->verify($factor, $currentCode);
        $this->assertTrue(true);
    }

    public function test_verify_throws_on_invalid_code(): void
    {
        $factor = $this->makeFactorWithSecret();

        $this->expectException(OtpInvalidException::class);
        $this->totpService->verify($factor, '000000');
    }

    public function test_verify_prevents_replay_of_same_code(): void
    {
        $factor = $this->makeFactorWithSecret();
        $currentCode = $this->google2fa->getCurrentOtp($factor->secret_encrypted);

        $this->totpService->verify($factor, $currentCode);

        $this->expectException(OtpInvalidException::class);
        $this->totpService->verify($factor, $currentCode);
    }

    private function makeFactorWithSecret(): Factor
    {
        $factor = new Factor;
        $factor->id = 1;
        $factor->setAttribute('user_id', 42);
        $factor->type = FactorType::AUTHENTICATOR_APP;
        $factor->secret_encrypted = $this->totpService->generateSecret();

        return $factor;
    }
}
