<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Exceptions\AccountLockedException;
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

        $this->app['config']->set('auth-security.lockout.max_attempts', 3);
        $this->app['config']->set('auth-security.lockout.window_minutes', 10);
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

    // ── recordFailedAttempt ──────────────────────────────────────────────────

    public function test_record_failed_attempt_does_not_lock_before_max_attempts(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1
        $this->service->recordFailedAttempt($this->user); // 2

        $this->assertFalse($this->service->isLocked($this->user));
    }

    public function test_record_failed_attempt_locks_on_reaching_max_attempts(): void
    {
        $this->expectException(AccountLockedException::class);

        $this->service->recordFailedAttempt($this->user); // 1
        $this->service->recordFailedAttempt($this->user); // 2
        $this->service->recordFailedAttempt($this->user); // 3 → bloqueia

        $this->assertTrue($this->service->isLocked($this->user));
    }

    public function test_lock_throws_account_locked_exception_with_locked_at(): void
    {
        $this->service->recordFailedAttempt($this->user);
        $this->service->recordFailedAttempt($this->user);

        try {
            $this->service->recordFailedAttempt($this->user);
            $this->fail('AccountLockedException expected.');
        } catch (AccountLockedException $exception) {
            $this->assertNotNull($exception->getLockedAt());
        }
    }

    public function test_record_failed_attempt_throws_immediately_when_already_locked(): void
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

    public function test_reset_attempts_allows_fresh_window_after_reset(): void
    {
        $this->service->recordFailedAttempt($this->user); // 1
        $this->service->recordFailedAttempt($this->user); // 2

        $this->service->resetAttempts($this->user);

        // Após reset, 2 tentativas não bloqueiam (max = 3)
        $this->service->recordFailedAttempt($this->user);
        $this->service->recordFailedAttempt($this->user);

        $this->assertFalse($this->service->isLocked($this->user));
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

    public function test_unlock_resets_attempt_counter(): void
    {
        UserState::create(['user_id' => 1, 'account_locked_at' => now()]);

        // Simula tentativas acumuladas antes do bloqueio
        $this->service->recordFailedAttempt(Mockery::mock(Authenticatable::class, [
            'getAuthIdentifier' => 2,
        ])); // outro usuário, não interfere

        $this->service->unlock($this->user, $this->admin);

        // Após desbloqueio, 2 tentativas não devem bloquear (max = 3)
        $this->service->recordFailedAttempt($this->user);
        $this->service->recordFailedAttempt($this->user);

        $this->assertFalse($this->service->isLocked($this->user));
    }
}
