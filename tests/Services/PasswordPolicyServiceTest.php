<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Exceptions\PasswordPolicyException;
use Ae3\AuthSecurity\Models\PasswordHistory;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\PasswordPolicyService;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Mockery;

class PasswordPolicyServiceTest extends DatabaseTestCase
{
    private PasswordPolicyService $service;

    private Authenticatable $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PasswordPolicyService;

        $this->user = Mockery::mock(Authenticatable::class);
        $this->user->allows('getAuthIdentifier')->andReturn(1);
    }

    // ── validate ────────────────────────────────────────────────────────────

    public function test_validate_passes_valid_password(): void
    {
        // Uppercase + lowercase + digit + special = 4 classes; length 12
        $this->service->validate($this->user, 'StrongPass1!');

        $this->assertTrue(true);
    }

    public function test_validate_fails_on_short_password(): void
    {
        $this->app['config']->set('auth-security.password.min_length', 10);

        $exception = $this->catchPolicy(fn () => $this->service->validate($this->user, 'Short1!'));

        $this->assertContains('min_length:10', $exception->getViolations());
    }

    public function test_validate_fails_when_classes_insufficient(): void
    {
        $this->app['config']->set('auth-security.password.classes_required', 3);

        // Só lowercase + digits = 2 classes
        $exception = $this->catchPolicy(fn () => $this->service->validate($this->user, 'onlylower1'));

        $this->assertContains('classes_required:3', $exception->getViolations());
    }

    public function test_validate_passes_exact_classes_required(): void
    {
        $this->app['config']->set('auth-security.password.classes_required', 3);

        // Lowercase + uppercase + digit = 3 classes
        $this->service->validate($this->user, 'LowerUpper1');

        $this->assertTrue(true);
    }

    public function test_validate_fails_when_password_in_history(): void
    {
        $this->app['config']->set('auth-security.password.history_size', 3);
        $reusedPassword = 'StrongPass1!';

        PasswordHistory::create([
            'user_id' => 1,
            'password_hash' => Hash::make($reusedPassword),
        ]);

        $exception = $this->catchPolicy(fn () => $this->service->validate($this->user, $reusedPassword));

        $this->assertContains('password_in_history', $exception->getViolations());
    }

    public function test_validate_passes_when_history_is_empty(): void
    {
        $this->app['config']->set('auth-security.password.history_size', 3);

        $this->service->validate($this->user, 'StrongPass1!');

        $this->assertTrue(true);
    }

    public function test_validate_allows_password_beyond_history_window(): void
    {
        $this->app['config']->set('auth-security.password.history_size', 2);
        $oldPassword = 'OldPassword1!';

        // 2 senhas mais recentes que a antiga
        PasswordHistory::create(['user_id' => 1, 'password_hash' => Hash::make($oldPassword)]);
        PasswordHistory::create(['user_id' => 1, 'password_hash' => Hash::make('Newer0ne!1')]);
        PasswordHistory::create(['user_id' => 1, 'password_hash' => Hash::make('Newest0ne!2')]);

        // $oldPassword está fora da janela de 2 — deve passar
        $this->service->validate($this->user, $oldPassword);

        $this->assertTrue(true);
    }

    public function test_validate_skips_history_check_when_history_size_zero(): void
    {
        $this->app['config']->set('auth-security.password.history_size', 0);
        $reusedPassword = 'StrongPass1!';

        PasswordHistory::create(['user_id' => 1, 'password_hash' => Hash::make($reusedPassword)]);

        // history_size=0 desativa verificação; não deve lançar
        $this->service->validate($this->user, $reusedPassword);

        $this->assertTrue(true);
    }

    public function test_validate_accumulates_multiple_violations(): void
    {
        $this->app['config']->set('auth-security.password.min_length', 20);
        $this->app['config']->set('auth-security.password.classes_required', 4);

        // Senha curta e de uma só classe (apenas letras minúsculas)
        $exception = $this->catchPolicy(fn () => $this->service->validate($this->user, 'short'));

        $violations = $exception->getViolations();
        $this->assertContains('min_length:20', $violations);
        $this->assertContains('classes_required:4', $violations);
    }

    // ── record ──────────────────────────────────────────────────────────────

    public function test_record_creates_password_history_entry(): void
    {
        $hashedPassword = Hash::make('StrongPass1!');

        $this->service->record($this->user, $hashedPassword);

        $this->assertDatabaseHas('password_history', [
            'user_id' => 1,
            'password_hash' => $hashedPassword,
        ]);
    }

    public function test_record_purges_oldest_entries_beyond_history_size(): void
    {
        $this->app['config']->set('auth-security.password.history_size', 3);

        foreach (range(1, 3) as $iteration) {
            $this->service->record($this->user, Hash::make("Password{$iteration}!A"));
        }

        $this->assertCount(3, PasswordHistory::where('user_id', 1)->get());

        $this->service->record($this->user, Hash::make('NewPassword4!A'));

        // Deve manter apenas os 3 mais recentes
        $this->assertCount(3, PasswordHistory::where('user_id', 1)->get());
    }

    public function test_record_preserves_entries_for_other_users(): void
    {
        $otherUser = Mockery::mock(Authenticatable::class);
        $otherUser->allows('getAuthIdentifier')->andReturn(99);

        $this->app['config']->set('auth-security.password.history_size', 1);

        $this->service->record($otherUser, Hash::make('OtherPass1!'));
        $this->service->record($this->user, Hash::make('UserPass1!'));
        $this->service->record($this->user, Hash::make('UserPass2!'));

        // user 99 deve permanecer intacto
        $this->assertCount(1, PasswordHistory::where('user_id', 99)->get());
    }

    // ── isExpired ────────────────────────────────────────────────────────────

    public function test_is_expired_returns_false_when_no_state_exists(): void
    {
        $this->assertFalse($this->service->isExpired($this->user));
    }

    public function test_is_expired_returns_false_when_password_changed_at_is_null(): void
    {
        UserState::create(['user_id' => 1]);

        $this->assertFalse($this->service->isExpired($this->user));
    }

    public function test_is_expired_returns_false_when_within_expiration_window(): void
    {
        $this->app['config']->set('auth-security.password.expiration_days', 90);

        UserState::create(['user_id' => 1, 'password_changed_at' => now()->subDays(30)]);

        $this->assertFalse($this->service->isExpired($this->user));
    }

    public function test_is_expired_returns_true_when_past_expiration(): void
    {
        $this->app['config']->set('auth-security.password.expiration_days', 90);

        UserState::create(['user_id' => 1, 'password_changed_at' => now()->subDays(91)]);

        $this->assertTrue($this->service->isExpired($this->user));
    }

    public function test_is_expired_returns_false_when_expiration_days_is_zero(): void
    {
        $this->app['config']->set('auth-security.password.expiration_days', 0);

        UserState::create(['user_id' => 1, 'password_changed_at' => now()->subDays(500)]);

        $this->assertFalse($this->service->isExpired($this->user));
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function catchPolicy(callable $callback): PasswordPolicyException
    {
        try {
            $callback();
            $this->fail('PasswordPolicyException expected but not thrown.');
        } catch (PasswordPolicyException $exception) {
            return $exception;
        }
    }
}
