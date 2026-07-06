<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\DataRetentionService;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Ae3\AuthSecurity\Tests\Support\TestUser;

class DataRetentionServiceTest extends DatabaseTestCase
{
    private DataRetentionService $service;

    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new DataRetentionService;
        $this->user = TestUser::create(['email' => 'retention@example.com', 'password' => bcrypt('Password1!Abc')]);
    }

    public function test_purges_pending_factor_older_than_retention_window(): void
    {
        config(['auth-security.retention.pending_factors_days' => 7]);

        $stale = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'stale@example.com',
            'confirmed_at' => null,
        ]);
        $stale->forceFill(['created_at' => now()->subDays(10)])->save();

        $recent = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::SMS,
            'identifier' => '+5511999999999',
            'confirmed_at' => null,
        ]);

        $purgedCount = $this->service->purgeStalePendingFactors();

        $this->assertSame(1, $purgedCount);
        $this->assertDatabaseMissing('factors', ['id' => $stale->id]);
        $this->assertDatabaseHas('factors', ['id' => $recent->id]);
    }

    public function test_does_not_purge_confirmed_factors(): void
    {
        config(['auth-security.retention.pending_factors_days' => 7]);

        $confirmed = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'confirmed@example.com',
            'confirmed_at' => now(),
        ]);
        $confirmed->forceFill(['created_at' => now()->subDays(30)])->save();

        $purgedCount = $this->service->purgeStalePendingFactors();

        $this->assertSame(0, $purgedCount);
        $this->assertDatabaseHas('factors', ['id' => $confirmed->id]);
    }

    public function test_does_not_purge_pending_factors_when_retention_disabled(): void
    {
        config(['auth-security.retention.pending_factors_days' => null]);

        $stale = Factor::create([
            'user_id' => $this->user->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'stale@example.com',
            'confirmed_at' => null,
        ]);
        $stale->forceFill(['created_at' => now()->subDays(365)])->save();

        $purgedCount = $this->service->purgeStalePendingFactors();

        $this->assertSame(0, $purgedCount);
        $this->assertDatabaseHas('factors', ['id' => $stale->id]);
    }

    public function test_purges_terminal_assisted_recovery_older_than_retention_window(): void
    {
        config(['auth-security.retention.assisted_recoveries_days' => 30]);

        $completed = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::COMPLETED,
            'requested_at' => now()->subDays(60),
            'completed_at' => now()->subDays(45),
        ]);
        $completed->forceFill(['updated_at' => now()->subDays(45)])->save();

        $pending = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::REQUESTED,
            'requested_at' => now()->subDays(60),
        ]);
        $pending->forceFill(['updated_at' => now()->subDays(60)])->save();

        $purgedCount = $this->service->purgeStaleAssistedRecoveries();

        $this->assertSame(1, $purgedCount);
        $this->assertDatabaseMissing('assisted_recoveries', ['id' => $completed->id]);
        $this->assertDatabaseHas('assisted_recoveries', ['id' => $pending->id]);
    }

    public function test_does_not_purge_assisted_recoveries_when_retention_disabled(): void
    {
        config(['auth-security.retention.assisted_recoveries_days' => null]);

        $completed = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::COMPLETED,
            'requested_at' => now()->subDays(365),
            'completed_at' => now()->subDays(365),
        ]);
        $completed->forceFill(['updated_at' => now()->subDays(365)])->save();

        $purgedCount = $this->service->purgeStaleAssistedRecoveries();

        $this->assertSame(0, $purgedCount);
        $this->assertDatabaseHas('assisted_recoveries', ['id' => $completed->id]);
    }
}
