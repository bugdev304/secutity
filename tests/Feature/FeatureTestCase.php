<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Ae3\AuthSecurity\Tests\Support\FakeMfaAuditLogger;
use Ae3\AuthSecurity\Tests\Support\FakeMfaContextResolver;
use Ae3\AuthSecurity\Tests\Support\FakeMfaMessageSender;
use Ae3\AuthSecurity\Tests\Support\FakeMfaRoleResolver;
use Ae3\AuthSecurity\Tests\Support\FakeMfaTenantResolver;
use Ae3\AuthSecurity\Tests\Support\TestUser;
use Illuminate\Support\Facades\Route;

abstract class FeatureTestCase extends DatabaseTestCase
{
    protected TestUser $user;

    protected FakeMfaMessageSender $messageSender;

    protected FakeMfaAuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageSender = new FakeMfaMessageSender;
        $this->auditLogger = new FakeMfaAuditLogger;

        $this->app->instance(MfaMessageSender::class, $this->messageSender);
        $this->app->instance(MfaAuditLogger::class, $this->auditLogger);
        $this->app->instance(MfaTenantResolver::class, new FakeMfaTenantResolver);
        $this->app->instance(MfaRoleResolver::class, new FakeMfaRoleResolver);
        $this->app->instance(MfaContextResolver::class, new FakeMfaContextResolver);

        Route::prefix('test-api')
            ->middleware(['web'])
            ->group(__DIR__.'/../../src/Http/routes.php');

        $this->user = TestUser::create([
            'email' => 'test@example.com',
            'password' => bcrypt('Password1!Abc'),
        ]);

        $this->actingAs($this->user);
    }
}
