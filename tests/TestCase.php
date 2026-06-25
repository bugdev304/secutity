<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests;

use Ae3\AuthSecurity\AuthSecurityServiceProvider;
use Ae3\AuthSecurity\Facades\AuthSecurity;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            AuthSecurityServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'AuthSecurity' => AuthSecurity::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth-security.schema', 'auth_security');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }
}
