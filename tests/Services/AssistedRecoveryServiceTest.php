<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryExpiredException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidStatusException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidTokenException;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Services\AssistedRecoveryService;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;

class AssistedRecoveryServiceTest extends DatabaseTestCase
{
    private AssistedRecoveryService $service;

    private Authenticatable $targetUser;

    private Authenticatable $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AssistedRecoveryService;

        $this->targetUser = Mockery::mock(Authenticatable::class);
        $this->targetUser->allows('getAuthIdentifier')->andReturn(1);

        $this->admin = Mockery::mock(Authenticatable::class);
        $this->admin->allows('getAuthIdentifier')->andReturn(99);
    }

    // ── request ─────────────────────────────────────────────────────────────

    public function test_request_creates_assisted_recovery_with_requested_status(): void
    {
        $recovery = $this->service->request($this->targetUser, AssistedRecoveryReason::DeviceLost);

        $this->assertEquals(AssistedRecoveryStatus::Requested, $recovery->status);
        $this->assertEquals(1, $recovery->target_user_id);
        $this->assertEquals(AssistedRecoveryReason::DeviceLost, $recovery->reason_category);
        $this->assertNotNull($recovery->requested_at);
    }

    public function test_request_stores_reason_text_when_provided(): void
    {
        $recovery = $this->service->request($this->targetUser, AssistedRecoveryReason::Other, 'Dispositivo destruído');

        $this->assertEquals('Dispositivo destruído', $recovery->reason_text);
    }

    public function test_request_stores_null_reason_text_when_not_provided(): void
    {
        $recovery = $this->service->request($this->targetUser, AssistedRecoveryReason::DeviceLost);

        $this->assertNull($recovery->reason_text);
    }

    // ── release ─────────────────────────────────────────────────────────────

    public function test_release_returns_plain_token(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $token = $this->service->release($recovery, $this->admin);

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function test_release_changes_status_to_released(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->service->release($recovery, $this->admin);

        $this->assertEquals(AssistedRecoveryStatus::Released, $recovery->fresh()->status);
    }

    public function test_release_sets_token_hash_and_expiry(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->service->release($recovery, $this->admin);

        $fresh = $recovery->fresh();
        $this->assertNotNull($fresh->recovery_token_hash);
        $this->assertNotNull($fresh->token_expires_at);
    }

    public function test_release_records_admin_user(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->service->release($recovery, $this->admin);

        $this->assertEquals(99, $recovery->fresh()->executed_by_user_id);
    }

    public function test_release_throws_when_already_terminal(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Completed);

        $this->expectException(AssistedRecoveryInvalidStatusException::class);
        $this->service->release($recovery, $this->admin);
    }

    public function test_release_throws_when_already_released(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Released);

        $this->expectException(AssistedRecoveryInvalidStatusException::class);
        $this->service->release($recovery, $this->admin);
    }

    // ── complete ─────────────────────────────────────────────────────────────

    public function test_complete_succeeds_with_valid_token(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);
        $token = $this->service->release($recovery, $this->admin);

        $this->service->complete($recovery->fresh(), $token);

        $this->assertEquals(AssistedRecoveryStatus::Completed, $recovery->fresh()->status);
    }

    public function test_complete_clears_token_hash_after_use(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);
        $token = $this->service->release($recovery, $this->admin);

        $this->service->complete($recovery->fresh(), $token);

        $this->assertNull($recovery->fresh()->recovery_token_hash);
    }

    public function test_complete_sets_must_register_factor(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);
        $token = $this->service->release($recovery, $this->admin);

        $this->service->complete($recovery->fresh(), $token);

        $state = UserState::where('user_id', 1)->first();
        $this->assertTrue($state->must_register_factor);
    }

    public function test_complete_throws_on_invalid_token(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);
        $this->service->release($recovery, $this->admin);

        $this->expectException(AssistedRecoveryInvalidTokenException::class);
        $this->service->complete($recovery->fresh(), 'wrong-token');
    }

    public function test_complete_throws_on_expired_token(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);
        $this->service->release($recovery, $this->admin);

        // Simula token expirado
        $recovery->fresh()->update(['token_expires_at' => now()->subHour()]);
        $freshExpired = $recovery->fresh();

        $this->expectException(AssistedRecoveryExpiredException::class);
        $this->service->complete($freshExpired, 'any-token');
    }

    public function test_complete_throws_when_status_not_released(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->expectException(AssistedRecoveryInvalidStatusException::class);
        $this->service->complete($recovery, 'any-token');
    }

    // ── refuse ───────────────────────────────────────────────────────────────

    public function test_refuse_changes_status_to_refused(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->service->refuse($recovery, $this->admin);

        $this->assertEquals(AssistedRecoveryStatus::Refused, $recovery->fresh()->status);
    }

    public function test_refuse_sets_refused_at(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Requested);

        $this->service->refuse($recovery, $this->admin);

        $this->assertNotNull($recovery->fresh()->refused_at);
    }

    public function test_refuse_throws_when_already_terminal(): void
    {
        $recovery = $this->createRecovery(AssistedRecoveryStatus::Completed);

        $this->expectException(AssistedRecoveryInvalidStatusException::class);
        $this->service->refuse($recovery, $this->admin);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function createRecovery(AssistedRecoveryStatus $status): AssistedRecovery
    {
        return AssistedRecovery::create([
            'target_user_id' => 1,
            'reason_category' => AssistedRecoveryReason::DeviceLost,
            'status' => $status,
            'requested_at' => now(),
        ]);
    }
}
