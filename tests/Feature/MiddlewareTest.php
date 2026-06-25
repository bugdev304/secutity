<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Http\Middleware\EnsureAccountNotLocked;
use Ae3\AuthSecurity\Http\Middleware\EnsureMfaCompleted;
use Ae3\AuthSecurity\Http\Middleware\EnsureMustRegisterFactorCompleted;
use Ae3\AuthSecurity\Http\Middleware\EnsurePasswordNotExpired;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class MiddlewareTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', EnsureAccountNotLocked::class])
            ->get('/test-not-locked', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', EnsurePasswordNotExpired::class])
            ->get('/test-password-not-expired', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', EnsureMfaCompleted::class])
            ->get('/test-mfa-completed', fn () => response()->json(['ok' => true]));

        Route::middleware(['web', EnsureMustRegisterFactorCompleted::class])
            ->get('/test-must-register', fn () => response()->json(['ok' => true]));
    }

    // ── EnsureAccountNotLocked ───────────────────────────────────────────────

    public function test_not_locked_middleware_passes_when_account_is_unlocked(): void
    {
        $response = $this->getJson('/test-not-locked');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_not_locked_middleware_blocks_when_account_is_locked(): void
    {
        UserState::create([
            'user_id' => $this->user->id,
            'account_locked_at' => now(),
        ]);

        $response = $this->getJson('/test-not-locked');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('code', 'ACCOUNT_LOCKED');
    }

    // ── EnsurePasswordNotExpired ─────────────────────────────────────────────

    public function test_password_not_expired_middleware_passes_when_no_expiration(): void
    {
        $this->app['config']->set('auth-security.password_policy.expiration_days', 0);

        $response = $this->getJson('/test-password-not-expired');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_password_not_expired_middleware_blocks_expired_password(): void
    {
        $this->app['config']->set('auth-security.password_policy.expiration_days', 90);

        UserState::create([
            'user_id' => $this->user->id,
            'password_changed_at' => now()->subDays(91),
        ]);

        $response = $this->getJson('/test-password-not-expired');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('code', 'PASSWORD_EXPIRED');
    }

    // ── EnsureMfaCompleted ───────────────────────────────────────────────────

    public function test_mfa_completed_middleware_blocks_without_session_token(): void
    {
        $response = $this->getJson('/test-mfa-completed');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('code', 'MFA_REQUIRED');
    }

    public function test_mfa_completed_middleware_passes_with_valid_session_token(): void
    {
        $mfaSessionService = $this->app->make(MfaSessionService::class);
        $sessionData = $mfaSessionService->create($this->user);

        $response = $this->getJson('/test-mfa-completed', [
            'X-Mfa-Session-Token' => $sessionData['mfa_session_token'],
        ]);

        $response->assertStatus(Response::HTTP_OK);
    }

    // ── EnsureMustRegisterFactorCompleted ────────────────────────────────────

    public function test_must_register_middleware_passes_when_not_required(): void
    {
        $response = $this->getJson('/test-must-register');

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_must_register_middleware_blocks_when_required(): void
    {
        UserState::create([
            'user_id' => $this->user->id,
            'must_register_factor' => true,
        ]);

        $response = $this->getJson('/test-must-register');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('code', 'MFA_FACTOR_REGISTRATION_REQUIRED');
    }
}
