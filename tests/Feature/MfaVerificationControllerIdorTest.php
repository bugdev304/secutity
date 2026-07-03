<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Ae3\AuthSecurity\Tests\Support\TestUser;
use Symfony\Component\HttpFoundation\Response;

class MfaVerificationControllerIdorTest extends FeatureTestCase
{
    private function createVictimFactor(): Factor
    {
        $victim = TestUser::create([
            'email' => 'victim@example.com',
            'password' => bcrypt('Password1!Abc'),
        ]);

        return Factor::create([
            'user_id' => $victim->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'victim@example.com',
            'confirmed_at' => now(),
        ]);
    }

    public function test_challenge_returns_not_found_for_factor_owned_by_another_user(): void
    {
        $factor = $this->createVictimFactor();

        $response = $this->postJson("/test-api/mfa/factors/{$factor->id}/challenge");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $this->assertCount(0, $this->messageSender->sent);
    }

    public function test_resend_returns_not_found_for_factor_owned_by_another_user(): void
    {
        $factor = $this->createVictimFactor();

        $response = $this->postJson("/test-api/mfa/factors/{$factor->id}/challenge/resend");

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $this->assertCount(0, $this->messageSender->sent);
    }

    public function test_verify_returns_not_found_for_factor_owned_by_another_user(): void
    {
        $factor = $this->createVictimFactor();

        $otpService = $this->app->make(OtpService::class);
        $code = $otpService->generate($factor);

        $response = $this->postJson('/test-api/mfa/verify', [
            'factor_id' => $factor->id,
            'factor_type' => 'email',
            'code' => $code,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }
}
