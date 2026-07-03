<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Symfony\Component\HttpFoundation\Response;

class MfaStateControllerTest extends FeatureTestCase
{
    public function test_returns_default_state_for_fresh_user(): void
    {
        $response = $this->getJson('/test-api/mfa/state');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.must_register_factor', false)
            ->assertJsonPath('data.mfa_required', true) // sem tenant resolvido: exige para todos
            ->assertJsonPath('data.mfa_satisfied', false)
            ->assertJsonPath('data.password_expired', false)
            ->assertJsonPath('data.account_locked', false)
            ->assertJsonPath('data.factors', [])
            ->assertJsonCount(1, 'data.contacts'); // TestUser::mfaContacts() retorna 1 contato
    }

    public function test_reflects_must_register_factor(): void
    {
        UserState::create([
            'user_id' => $this->user->id,
            'must_register_factor' => true,
        ]);

        $response = $this->getJson('/test-api/mfa/state');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.must_register_factor', true);
    }

    public function test_reflects_mfa_satisfied_with_valid_session_token(): void
    {
        $mfaSessionService = $this->app->make(MfaSessionService::class);
        $sessionData = $mfaSessionService->create($this->user);

        $response = $this->getJson('/test-api/mfa/state', [
            'X-Mfa-Session-Token' => $sessionData['mfa_session_token'],
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.mfa_satisfied', true);
    }

    public function test_reflects_account_locked(): void
    {
        UserState::create([
            'user_id' => $this->user->id,
            'account_locked_at' => now(),
        ]);

        $response = $this->getJson('/test-api/mfa/state');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.account_locked', true);
    }

    public function test_reflects_password_expired(): void
    {
        $this->app['config']->set('auth-security.password.expiration_days', 90);

        UserState::create([
            'user_id' => $this->user->id,
            'password_changed_at' => now()->subDays(200),
        ]);

        $response = $this->getJson('/test-api/mfa/state');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.password_expired', true);
    }

    public function test_lists_confirmed_factors(): void
    {
        Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'test@example.com',
            'confirmed_at' => now(),
        ]);

        $response = $this->getJson('/test-api/mfa/state');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data.factors')
            ->assertJsonPath('data.factors.0.type', 'email');
    }
}
