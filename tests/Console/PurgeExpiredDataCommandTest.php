<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Console;

use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Ae3\AuthSecurity\Tests\Support\TestUser;

class PurgeExpiredDataCommandTest extends DatabaseTestCase
{
    public function test_command_purges_stale_pending_factors_and_reports_counts(): void
    {
        config(['auth-security.retention.pending_factors_days' => 7]);

        $user = TestUser::create(['email' => 'command@example.com', 'password' => bcrypt('Password1!Abc')]);

        $stale = Factor::create([
            'user_id' => $user->id,
            'type' => FactorType::EMAIL,
            'identifier' => 'stale@example.com',
            'confirmed_at' => null,
        ]);
        $stale->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->artisan('auth-security:purge-expired-data')
            ->expectsOutputToContain('Fatores pendentes eliminados: 1')
            ->expectsOutputToContain('Recuperações assistidas eliminadas: 0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('factors', ['id' => $stale->id]);
    }
}
