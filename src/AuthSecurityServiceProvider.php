<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryExpiredException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidStatusException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidTokenException;
use Ae3\AuthSecurity\Exceptions\AuthSecurityException;
use Ae3\AuthSecurity\Exceptions\LastFactorRemovalException;
use Ae3\AuthSecurity\Exceptions\OtpExpiredException;
use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Exceptions\OtpResendLimitException;
use Ae3\AuthSecurity\Exceptions\OtpResendTooSoonException;
use Ae3\AuthSecurity\Exceptions\PasswordPolicyException;
use Ae3\AuthSecurity\Exceptions\PolicyBelowFloorException;
use Ae3\AuthSecurity\Exceptions\RecoveryCodeInvalidException;
use Ae3\AuthSecurity\Http\Middleware\EnsureAccountNotLocked;
use Ae3\AuthSecurity\Http\Middleware\EnsureMfaCompleted;
use Ae3\AuthSecurity\Http\Middleware\EnsureMustRegisterFactorCompleted;
use Ae3\AuthSecurity\Http\Middleware\EnsurePasswordNotExpired;
use Ae3\AuthSecurity\Listeners\DispatchAuditLogListener;
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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthSecurityServiceProvider extends ServiceProvider
{
    private const CONTRACT_MAP = [
        'tenant_resolver' => MfaTenantResolver::class,
        'role_resolver' => MfaRoleResolver::class,
        'context_resolver' => MfaContextResolver::class,
        'message_sender' => MfaMessageSender::class,
        'audit_logger' => MfaAuditLogger::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auth-security.php', 'auth-security');

        $this->app->singleton(AuthSecurity::class, fn () => new AuthSecurity);

        $this->registerContracts();
    }

    public function boot(): void
    {
        $this->bootPublishes();
        $this->bootObservers();
        $this->bootEventListeners();
        $this->bootRateLimiters();
        $this->bootMiddlewareAliases();
        $this->bootExceptionRendering();
        $this->bootContractValidation();
    }

    /** Registra as rotas do pacote sob um prefixo configurável. Chamar no routes/api.php da app. */
    public static function routes(?string $prefix = null, array $middleware = []): void
    {
        $prefix = $prefix ?? config('auth-security.routes.prefix', 'auth-security');

        Route::prefix($prefix)
            ->middleware(array_merge(['api', 'auth:sanctum'], $middleware))
            ->group(__DIR__.'/Http/routes.php');
    }

    private function registerContracts(): void
    {
        foreach (self::CONTRACT_MAP as $configKey => $contractInterface) {
            $implementation = config("auth-security.{$configKey}");

            if ($implementation !== null) {
                $this->app->singleton($contractInterface, $implementation);
            }
        }
    }

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

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'auth-security-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/auth-security'),
        ], 'auth-security-lang');
    }

    private function bootEventListeners(): void
    {
        if ($this->app->bound(MfaAuditLogger::class)) {
            Event::subscribe(DispatchAuditLogListener::class);
        }
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

    private function bootRateLimiters(): void
    {
        RateLimiter::for('auth-security:verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:send-otp', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:generate-recovery', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:assisted-recovery', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
    }

    private function bootMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('auth-security.mfa', EnsureMfaCompleted::class);
        $router->aliasMiddleware('auth-security.not-locked', EnsureAccountNotLocked::class);
        $router->aliasMiddleware('auth-security.password-not-expired', EnsurePasswordNotExpired::class);
        $router->aliasMiddleware('auth-security.must-register-factor', EnsureMustRegisterFactorCompleted::class);
    }

    private function bootExceptionRendering(): void
    {
        $this->app->make('Illuminate\Contracts\Debug\ExceptionHandler')
            ->renderable(function (AuthSecurityException $exception, Request $request) {
                if (! $request->expectsJson()) {
                    return null;
                }

                return $this->renderAuthSecurityException($exception, $request);
            });
    }

    private function renderAuthSecurityException(AuthSecurityException $exception, Request $request): JsonResponse
    {
        [$status, $code, $extra] = $this->resolveExceptionDetails($exception);

        $payload = array_filter([
            'message' => $exception->getMessage(),
            'code' => $code,
            ...$extra,
        ]);

        return response()->json($payload, $status);
    }

    private function resolveExceptionDetails(AuthSecurityException $exception): array
    {
        return match (true) {
            $exception instanceof OtpExpiredException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'INVALID_CODE', []],
            $exception instanceof OtpInvalidException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'INVALID_CODE', ['remaining_attempts' => $exception->getRemainingAttempts()]],
            $exception instanceof OtpResendLimitException => [Response::HTTP_TOO_MANY_REQUESTS, 'RESEND_RATE_LIMITED', []],
            $exception instanceof OtpResendTooSoonException => [Response::HTTP_TOO_MANY_REQUESTS, 'RESEND_RATE_LIMITED', ['retry_after_seconds' => $exception->getSecondsRemaining()]],
            $exception instanceof RecoveryCodeInvalidException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'INVALID_CODE', []],
            $exception instanceof PasswordPolicyException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'WEAK_PASSWORD', ['violations' => $exception->getViolations()]],
            $exception instanceof PolicyBelowFloorException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'BELOW_FLOOR', ['conflicts' => $exception->getConflicts()]],
            $exception instanceof LastFactorRemovalException => [Response::HTTP_CONFLICT, 'LAST_FACTOR_REQUIRED', []],
            $exception instanceof AssistedRecoveryInvalidStatusException => [Response::HTTP_CONFLICT, 'INVALID_STATUS', []],
            $exception instanceof AssistedRecoveryInvalidTokenException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'INVALID_TOKEN', []],
            $exception instanceof AssistedRecoveryExpiredException => [Response::HTTP_UNPROCESSABLE_ENTITY, 'TOKEN_EXPIRED', []],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, 'AUTH_SECURITY_ERROR', []],
        };
    }
}
