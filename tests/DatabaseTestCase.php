<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Base para testes que precisam de banco de dados.
 * Usa SQLite in-memory com migrations simplificadas (sem schema PostgreSQL).
 */
abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Schema vazio desativa o prefixo em AuthSecurityModel — compatível com SQLite
        $app['config']->set('auth-security.schema', '');

        // Reduz custo do bcrypt em testes para evitar lentidão
        Hash::setRounds(4);
    }
}
