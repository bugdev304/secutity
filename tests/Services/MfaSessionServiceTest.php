<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Services\MfaSessionService;
use Ae3\AuthSecurity\Tests\Support\TestUser;
use Ae3\AuthSecurity\Tests\TestCase;
use Carbon\Carbon;

class MfaSessionServiceTest extends TestCase
{
    public function test_create_uses_default_session_ttl_from_config(): void
    {
        $sessionTtlHours = config('auth-security.mfa.session_ttl_hours', 8);
        $user = new TestUser;
        $user->id = 1;

        $sessionData = (new MfaSessionService)->create($user);

        $expectedExpiresAt = now()->addHours($sessionTtlHours);
        $this->assertEqualsWithDelta(
            $expectedExpiresAt->timestamp,
            Carbon::parse($sessionData['expires_at'])->timestamp,
            1,
        );
    }

    public function test_create_respects_custom_session_ttl_from_config(): void
    {
        $this->app['config']->set('auth-security.mfa.session_ttl_hours', 1);

        $user = new TestUser;
        $user->id = 1;

        $sessionData = (new MfaSessionService)->create($user);

        $expectedExpiresAt = now()->addHour();
        $this->assertEqualsWithDelta(
            $expectedExpiresAt->timestamp,
            Carbon::parse($sessionData['expires_at'])->timestamp,
            1,
        );
    }

    public function test_get_user_id_resolves_token_created_with_custom_ttl(): void
    {
        $this->app['config']->set('auth-security.mfa.session_ttl_hours', 1);

        $user = new TestUser;
        $user->id = 7;

        $mfaSessionService = new MfaSessionService;
        $sessionData = $mfaSessionService->create($user);

        $this->assertSame(7, $mfaSessionService->getUserId($sessionData['mfa_session_token']));
    }
}
