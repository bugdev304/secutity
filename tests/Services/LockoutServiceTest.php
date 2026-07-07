<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Exceptions\AccountLockedException;
use Ae3\AuthSecurity\Exceptions\TemporarilyThrottledException;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\LockoutService;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;

class LockoutServiceTest extends DatabaseTestCase
{
    private LockoutService $service;

    private Authenticatable $user;

    private Authenticatable $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LockoutService;

        $this->user = Mockery::mock(Authenticatable::class);
        $this->user->allows('getAuthIdentifier')->andReturn(1);

        $this->admin = Mockery::mock(Authenticatable::class);
        $this->admin->allows('getAuthIdentifier')->andReturn(99);

        // attempts_per_stage=2, backoff=[1, 3]: falha 2 → 1min; falha 4 → 3min; falha 6 → definitivo
        $this->app['config']->set('auth-security.lockout.attempts_per_stage', 2);
        $this->app['config']->set('auth-security.lockout.backoff_minutes', [1, 3]);
        $this->app['config']->set('auth-security.lockout.reset_after_minutes', 1440);
    }

    // ── isLocked ─────────────────────────────────────────────────────────────

    public function test_is_locked_returns_false_when_no_state_exists(): void
    {
        $this->assertFalse($this->service->isLocked($this->user));
    }

    public function test_is_locked_returns_false_when_account_not_locked(): void
    {
        UserState::create(['user_id' => 1]);

        $this->assertFalse($this->service->isLocked($this->user));
    }

    public function test_is_locked_returns_true_when_account_locked(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        $this->assertTrue($this->service->isLocked($this->user));
    }

    // ── recordFailedAttempt — estágios de backoff ───────────────────────────

    public function test_first_failed_attempt_does_not_throttle(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1

        $this->assertFalse($this->service->isLocked($this->user));
        $this->assertNull($this->service->throttledUntil($this->user));
    }

    public function test_reaching_first_stage_throttles_temporarily(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1

        $this->expectException(TemporarilyThrottledException::class);
        $this->service->recordFailedAttempt($this->user); // 2 → estágio 0 (1 min)
    }

    public function test_first_stage_throttle_sets_retry_after_within_configured_minutes(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1

        try {
            $this->service->recordFailedAttempt($this->user); // 2
            $this->fail('TemporarilyThrottledException expected.');
        } catch (TemporarilyThrottledException $exception) {
            $this->assertTrue($exception->getRetryAfter()->between(now(), now()->addMinutes(1)->addSecond()));
        }

        $this->assertFalse($this->service->isLocked($this->user));
        $this->assertNotNull($this->service->throttledUntil($this->user));
    }

    public function test_attempt_during_active_throttle_rethrows_same_retry_after(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1

        try {
            $this->service->recordFailedAttempt($this->user); // 2 → throttled
        } catch (TemporarilyThrottledException) {
        }

        $firstRetryAfter = $this->service->throttledUntil($this->user);

        try {
            $this->service->recordFailedAttempt($this->user); // ainda dentro do bloqueio
            $this->fail('TemporarilyThrottledException expected.');
        } catch (TemporarilyThrottledException $exception) {
            $this->assertEquals($firstRetryAfter->toIso8601String(), $exception->getRetryAfter()->toIso8601String());
        }
    }

    public function test_reaching_second_stage_throttles_with_longer_backoff(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1
        try {
            $this->service->recordFailedAttempt($this->user); // 2 → estágio 0 (1 min)
        } catch (TemporarilyThrottledException) {
        }

        // Estágio 0 (1 min) expira; contador de tentativas continua intacto
        $this->travel(61)->seconds();

        $this->service->recordFailedAttempt($this->user); // 3

        try {
            $this->service->recordFailedAttempt($this->user); // 4 → estágio 1 (3 min)
            $this->fail('TemporarilyThrottledException expected.');
        } catch (TemporarilyThrottledException $exception) {
            $this->assertTrue($exception->getRetryAfter()->between(now()->addMinutes(2), now()->addMinutes(3)->addSecond()));
        }
    }

    public function test_exhausting_backoff_stages_locks_account_permanently(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1
        $this->tryAndSwallow(fn () => $this->service->recordFailedAttempt($this->user)); // 2 → estágio 0 (1 min)
        $this->travel(61)->seconds();
        $this->service->recordFailedAttempt($this->user); // 3
        $this->tryAndSwallow(fn () => $this->service->recordFailedAttempt($this->user)); // 4 → estágio 1 (3 min)
        $this->travel(3)->minutes();
        $this->travel(1)->seconds();
        $this->service->recordFailedAttempt($this->user); // 5

        $this->expectException(AccountLockedException::class);
        $this->service->recordFailedAttempt($this->user); // 6 → estágio 2 não existe → bloqueio definitivo
    }

    public function test_locked_account_throws_immediately_regardless_of_attempt_count(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        $this->expectException(AccountLockedException::class);
        $this->service->recordFailedAttempt($this->user);
    }

    public function test_record_failed_attempt_exposes_locked_at_when_pre_locked(): void
    {
        $lockedAt = now()->subMinutes(5);
        UserState::create(['user_id' => 1, 'account_locked_at' => $lockedAt]);

        try {
            $this->service->recordFailedAttempt($this->user);
            $this->fail('AccountLockedException expected.');
        } catch (AccountLockedException $exception) {
            $this->assertNotNull($exception->getLockedAt());
        }
    }

    // ── resetAttempts ────────────────────────────────────────────────────────

    public function test_reset_attempts_clears_both_counter_and_active_throttle(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1
        $this->tryAndSwallow(fn () => $this->service->recordFailedAttempt($this->user)); // 2 → throttled

        $this->service->resetAttempts($this->user);

        $this->assertNull($this->service->throttledUntil($this->user));

        // Após reset, precisa de 2 novas falhas pra voltar ao 1º estágio
        $this->service->recordFailedAttempt($this->user);
        $this->assertNull($this->service->throttledUntil($this->user));
    }

    // ── lock ─────────────────────────────────────────────────────────────────

    public function test_lock_sets_account_locked_at_in_user_state(): void
    {
        $this->service->lock($this->user);

        $state = UserState::where('user_id', 1)->first();
        $this->assertNotNull($state);
        $this->assertTrue($state->isLocked());
    }

    public function test_lock_creates_user_state_if_missing(): void
    {
        $this->assertDatabaseMissing('user_state', ['user_id' => 1]);

        $this->service->lock($this->user);

        $this->assertDatabaseHas('user_state', ['user_id' => 1]);
    }

    // ── unlock ───────────────────────────────────────────────────────────────

    public function test_unlock_clears_account_locked_at(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        $this->service->unlock($this->user, $this->admin);

        $this->assertFalse($this->service->isLocked($this->user));
    }

    public function test_unlock_records_admin_who_unlocked(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        $this->service->unlock($this->user, $this->admin);

        $state = UserState::where('user_id', 1)->first();
        $this->assertEquals(99, $state->account_unlocked_by_user_id);
        $this->assertNotNull($state->account_unlocked_at);
    }

    public function test_unlock_resets_attempt_counter_and_throttle(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        $this->service->unlock($this->user, $this->admin);

        // Após desbloqueio, precisa de 2 novas falhas pra voltar ao 1º estágio
        $this->service->recordFailedAttempt($this->user);

        $this->assertFalse($this->service->isLocked($this->user));
        $this->assertNull($this->service->throttledUntil($this->user));
    }

    private function tryAndSwallow(callable $callback): void
    {
        try {
            $callback();
        } catch (TemporarilyThrottledException) {
        }
    }
}
