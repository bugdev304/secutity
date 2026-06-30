<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\OtpService;
use Ae3\AuthSecurity\Support\ContactTokenizer;
use Symfony\Component\HttpFoundation\Response;

class FactorControllerTest extends FeatureTestCase
{
    // ── GET /test-api/mfa/factors ────────────────────────────────────────────

    public function test_index_returns_empty_list_when_no_confirmed_factors(): void
    {
        $response = $this->getJson('/test-api/mfa/factors');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data', []);
    }

    public function test_index_returns_only_confirmed_factors(): void
    {
        Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Email,
            'identifier' => 'test@example.com',
            'confirmed_at' => now(),
        ]);
        Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Sms,
            'identifier' => '+5511999999999',
            'confirmed_at' => null, // pending
        ]);

        $response = $this->getJson('/test-api/mfa/factors');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'email');
    }

    // ── POST /test-api/mfa/factors (OTP) ────────────────────────────────────

    public function test_store_otp_creates_pending_factor_and_sends_otp(): void
    {
        $contactToken = ContactTokenizer::generate('email', $this->user->email);

        $response = $this->postJson('/test-api/mfa/factors', [
            'type'          => 'email',
            'contact_token' => $contactToken,
            'name'          => 'Work email',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('meta.enrollment_started', true);

        $this->assertDatabaseHas('factors', [
            'user_id'      => $this->user->id,
            'type'         => 'email',
            'identifier'   => $this->user->email,
            'confirmed_at' => null,
        ]);

        $this->assertCount(1, $this->messageSender->sent);
        $this->assertSame('email', $this->messageSender->sent[0]['channel']);
    }

    public function test_store_requires_contact_token_for_otp(): void
    {
        $response = $this->postJson('/test-api/mfa/factors', [
            'type' => 'email',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_store_rejects_invalid_contact_token(): void
    {
        $response = $this->postJson('/test-api/mfa/factors', [
            'type'          => 'email',
            'contact_token' => 'token-invalido',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_store_totp_returns_secret_and_qr_code(): void
    {
        $response = $this->postJson('/test-api/mfa/factors', [
            'type' => 'authenticator_app',
            'holder_name' => 'Test User',
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['data' => ['factor_id', 'secret', 'otpauth_uri', 'qr_code_svg']]);

        $this->assertDatabaseHas('factors', [
            'user_id' => $this->user->id,
            'type' => 'authenticator_app',
            'confirmed_at' => null,
        ]);
    }

    // ── POST /test-api/mfa/factors/{factor}/confirm ─────────────────────────

    public function test_confirm_marks_otp_factor_as_confirmed(): void
    {
        $factor = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Email,
            'identifier' => 'test@example.com',
            'confirmed_at' => null,
        ]);

        // Generate an OTP so we can confirm it
        $otpService = $this->app->make(OtpService::class);
        $code = $otpService->generate($factor);

        $response = $this->postJson("/test-api/mfa/factors/{$factor->id}/confirm", [
            'code' => $code,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.type', 'email');

        $this->assertNotNull(Factor::find($factor->id)->confirmed_at);
    }

    public function test_confirm_rejects_wrong_code_after_otp_generated(): void
    {
        $factor = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Email,
            'identifier' => 'test@example.com',
            'confirmed_at' => null,
        ]);

        // Generate a valid OTP so the factor has a pending code in cache
        $otpService = $this->app->make(OtpService::class);
        $otpService->generate($factor); // store 'correct' code in cache

        // Submit a wrong code — must get INVALID_CODE (not expired)
        $response = $this->postJson("/test-api/mfa/factors/{$factor->id}/confirm", [
            'code' => '000000',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('code', 'INVALID_CODE');
    }

    // ── DELETE /test-api/mfa/factors/{factor} ───────────────────────────────

    public function test_destroy_removes_factor_when_mfa_not_required(): void
    {
        $factor = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Email,
            'identifier' => 'test@example.com',
            'confirmed_at' => now(),
        ]);

        $response = $this->deleteJson("/test-api/mfa/factors/{$factor->id}");

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('factors', ['id' => $factor->id]);
    }

    // ── GET /test-api/mfa/factors/alternatives ──────────────────────────────

    public function test_alternatives_returns_other_confirmed_factors(): void
    {
        $factor1 = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Email,
            'identifier' => 'a@example.com',
            'confirmed_at' => now(),
        ]);
        $factor2 = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::Sms,
            'identifier' => '+5511999999999',
            'confirmed_at' => now(),
        ]);

        $response = $this->getJson("/test-api/mfa/factors/alternatives?exclude_factor_id={$factor1->id}");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data.factors')
            ->assertJsonPath('data.factors.0.type', 'sms');
    }
}
