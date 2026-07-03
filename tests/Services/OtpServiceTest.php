<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Exceptions\OtpExpiredException;
use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Exceptions\OtpResendLimitException;
use Ae3\AuthSecurity\Exceptions\OtpResendTooSoonException;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Ae3\AuthSecurity\Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private OtpService $otpService;

    private Factor $factor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->otpService = new OtpService;

        $this->factor = new Factor;
        $this->factor->id = 1;
        $this->factor->setAttribute('user_id', 42);
        $this->factor->type = FactorType::EMAIL;
        $this->factor->identifier = 'test@example.com';
    }

    public function test_generate_returns_numeric_code_of_configured_length(): void
    {
        $code = $this->otpService->generate($this->factor);

        $expectedLength = config('auth-security.mfa.otp_length', 6);
        $this->assertMatchesRegularExpression('/^\d{'.$expectedLength.'}$/', $code);
    }

    public function test_verify_succeeds_with_correct_code(): void
    {
        $code = $this->otpService->generate($this->factor);

        // Sem exceção = sucesso
        $this->otpService->verify($this->factor, $code);
        $this->assertTrue(true);
    }

    public function test_verify_throws_otp_expired_when_no_otp_in_cache(): void
    {
        $this->expectException(OtpExpiredException::class);
        $this->otpService->verify($this->factor, '123456');
    }

    public function test_verify_throws_otp_invalid_with_wrong_code(): void
    {
        $this->otpService->generate($this->factor);

        $this->expectException(OtpInvalidException::class);
        $this->otpService->verify($this->factor, '000000');
    }

    public function test_otp_is_single_use(): void
    {
        $code = $this->otpService->generate($this->factor);
        $this->otpService->verify($this->factor, $code);

        $this->expectException(OtpExpiredException::class);
        $this->otpService->verify($this->factor, $code);
    }

    public function test_can_resend_returns_true_when_no_otp_generated(): void
    {
        $this->assertTrue($this->otpService->canResend($this->factor));
    }

    public function test_can_resend_returns_false_immediately_after_generate(): void
    {
        $this->otpService->generate($this->factor);

        $this->assertFalse($this->otpService->canResend($this->factor));
    }

    public function test_can_resend_returns_true_after_interval_passes(): void
    {
        $this->otpService->generate($this->factor);

        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds', 30);
        $this->travel($intervalSeconds + 1)->seconds();

        $this->assertTrue($this->otpService->canResend($this->factor));
    }

    public function test_resend_too_soon_throws_exception(): void
    {
        $this->otpService->generate($this->factor);

        $this->expectException(OtpResendTooSoonException::class);
        $this->otpService->generate($this->factor);
    }

    public function test_resend_too_soon_exception_carries_seconds_remaining(): void
    {
        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds', 30);
        $this->otpService->generate($this->factor);

        $this->travel(10)->seconds();

        try {
            $this->otpService->generate($this->factor);
            $this->fail('Expected OtpResendTooSoonException');
        } catch (OtpResendTooSoonException $exception) {
            $this->assertGreaterThan(0, $exception->getSecondsRemaining());
            $this->assertLessThanOrEqual($intervalSeconds, $exception->getSecondsRemaining());
        }
    }

    public function test_resend_limit_throws_exception_after_max_resends(): void
    {
        $resendLimit = config('auth-security.mfa.otp_resend_limit', 3);
        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds', 30);

        $this->otpService->generate($this->factor);

        for ($resendNumber = 0; $resendNumber < $resendLimit; $resendNumber++) {
            $this->travel($intervalSeconds + 1)->seconds();
            $this->otpService->generate($this->factor);
        }

        $this->travel($intervalSeconds + 1)->seconds();

        $this->expectException(OtpResendLimitException::class);
        $this->otpService->generate($this->factor);
    }

    public function test_can_resend_returns_false_when_limit_exhausted(): void
    {
        $resendLimit = config('auth-security.mfa.otp_resend_limit', 3);
        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds', 30);

        $this->otpService->generate($this->factor);

        for ($resendNumber = 0; $resendNumber < $resendLimit; $resendNumber++) {
            $this->travel($intervalSeconds + 1)->seconds();
            $this->otpService->generate($this->factor);
        }

        $this->travel($intervalSeconds + 1)->seconds();

        $this->assertFalse($this->otpService->canResend($this->factor));
    }

    public function test_fresh_generate_after_otp_expires_resets_resend_count(): void
    {
        $validityMinutes = config('auth-security.mfa.otp_validity_minutes', 10);

        $this->otpService->generate($this->factor);

        // Deixa o OTP expirar
        $this->travel($validityMinutes + 1)->minutes();

        // Nova geração deve ser livre de restrições de reenvio
        $code = $this->otpService->generate($this->factor);
        $this->assertNotEmpty($code);
    }

    public function test_verify_reports_decreasing_remaining_attempts(): void
    {
        $maxAttempts = config('auth-security.mfa.otp_max_attempts', 5);
        $this->otpService->generate($this->factor);

        try {
            $this->otpService->verify($this->factor, '000000');
            $this->fail('Expected OtpInvalidException');
        } catch (OtpInvalidException $exception) {
            $this->assertSame($maxAttempts - 1, $exception->getRemainingAttempts());
        }

        try {
            $this->otpService->verify($this->factor, '000000');
            $this->fail('Expected OtpInvalidException');
        } catch (OtpInvalidException $exception) {
            $this->assertSame($maxAttempts - 2, $exception->getRemainingAttempts());
        }
    }

    public function test_verify_invalidates_otp_after_max_attempts_exhausted(): void
    {
        $maxAttempts = config('auth-security.mfa.otp_max_attempts', 5);
        $code = $this->otpService->generate($this->factor);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $this->otpService->verify($this->factor, '000000');
            } catch (OtpInvalidException) {
                // esperado até esgotar as tentativas
            }
        }

        // OTP foi invalidado ao esgotar as tentativas — mesmo o código correto falha agora
        $this->expectException(OtpExpiredException::class);
        $this->otpService->verify($this->factor, $code);
    }

    public function test_verify_success_resets_attempts_counter(): void
    {
        $intervalSeconds = config('auth-security.mfa.otp_resend_interval_seconds', 30);
        $this->otpService->generate($this->factor);

        try {
            $this->otpService->verify($this->factor, '000000');
        } catch (OtpInvalidException) {
            // consome uma tentativa de propósito
        }

        $this->travel($intervalSeconds + 1)->seconds();

        $code = $this->otpService->generate($this->factor);
        $this->otpService->verify($this->factor, $code);
        $this->assertTrue(true);
    }
}
