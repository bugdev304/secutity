<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity;

use Illuminate\Support\ServiceProvider;

class AuthSecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/auth-security.php',
            'auth-security',
        );

        $this->app->singleton(AuthSecurity::class, fn () => new AuthSecurity);
    }

    public function boot(): void
    {
        $this->bootPublishes();
    }

    private function bootPublishes(): void
    {
        $this->publishes([
            __DIR__.'/../config/auth-security.php' => config_path('auth-security.php'),
        ], 'auth-security-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'auth-security-migrations');

        $this->publishesMigrations([
            __DIR__.'/../database/user-columns' => database_path('migrations'),
        ], 'auth-security-user-columns');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/auth-security'),
        ], 'auth-security-lang');
    }
}
