<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity;

use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Ae3\AuthSecurity\Models\PasswordHistory;
use Ae3\AuthSecurity\Models\RecoveryCode;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Observers\AssistedRecoveryObserver;
use Ae3\AuthSecurity\Observers\FactorObserver;
use Ae3\AuthSecurity\Observers\OrganizationPolicyObserver;
use Ae3\AuthSecurity\Observers\PasswordHistoryObserver;
use Ae3\AuthSecurity\Observers\RecoveryCodeObserver;
use Ae3\AuthSecurity\Observers\UserStateObserver;
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
        $this->bootObservers();
    }

    private function bootPublishes(): void
    {
        $this->publishes([
            __DIR__.'/../config/auth-security.php' => config_path('auth-security.php'),
        ], 'auth-security-config');

        // P2.B: user_state é parte das migrations core; auth-security-user-columns removido.
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'auth-security-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/auth-security'),
        ], 'auth-security-lang');
    }

    // Registrado via ServiceProvider (não #[ObservedBy]) para compatibilidade com Laravel 10.
    private function bootObservers(): void
    {
        Factor::observe(FactorObserver::class);
        RecoveryCode::observe(RecoveryCodeObserver::class);
        OrganizationPolicy::observe(OrganizationPolicyObserver::class);
        AssistedRecovery::observe(AssistedRecoveryObserver::class);
        PasswordHistory::observe(PasswordHistoryObserver::class);
        UserState::observe(UserStateObserver::class);
    }
}
