<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
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
use RuntimeException;

class AuthSecurityServiceProvider extends ServiceProvider
{
    /**
     * Contratos obrigatórios: chave de config → interface do pacote.
     * Usados tanto para binding automático quanto para validação no boot.
     */
    private const CONTRACT_MAP = [
        'tenant_resolver' => MfaTenantResolver::class,
        'role_resolver' => MfaRoleResolver::class,
        'context_resolver' => MfaContextResolver::class,
        'message_sender' => MfaMessageSender::class,
        'audit_logger' => MfaAuditLogger::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/auth-security.php',
            'auth-security',
        );

        $this->app->singleton(AuthSecurity::class, fn () => new AuthSecurity);

        $this->registerContracts();
    }

    public function boot(): void
    {
        $this->bootPublishes();
        $this->bootObservers();
        $this->bootContractValidation();
    }

    // Vincula cada contrato à implementação configurada pela app consumidora.
    private function registerContracts(): void
    {
        foreach (self::CONTRACT_MAP as $configKey => $contractInterface) {
            $implementation = config("auth-security.{$configKey}");

            if ($implementation !== null) {
                $this->app->singleton($contractInterface, $implementation);
            }
        }
    }

    // Falha cedo com mensagem clara quando contratos obrigatórios não estão vinculados.
    private function bootContractValidation(): void
    {
        if (! config('auth-security.require_contracts', true)) {
            return;
        }

        $missingContracts = [];

        foreach (self::CONTRACT_MAP as $configKey => $contractInterface) {
            if (! $this->app->bound($contractInterface)) {
                $missingContracts[] = "  - auth-security.{$configKey} ({$contractInterface})";
            }
        }

        if ($missingContracts !== []) {
            throw new RuntimeException(
                "ae3/auth-security: contratos obrigatórios não configurados:\n"
                .implode("\n", $missingContracts)
                ."\n\nConfigure cada contrato em config/auth-security.php ou via AppServiceProvider."
            );
        }
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
