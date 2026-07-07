<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity;

use Ae3\AuthSecurity\Console\Commands\PurgeExpiredDataCommand;
use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Defaults\NullMfaAuditLogger;
use Ae3\AuthSecurity\Defaults\NullMfaContextResolver;
use Ae3\AuthSecurity\Defaults\NullMfaMessageSender;
use Ae3\AuthSecurity\Defaults\NullMfaRoleResolver;
use Ae3\AuthSecurity\Defaults\NullMfaTenantResolver;
use Ae3\AuthSecurity\Enums\ErrorCode;
use Ae3\AuthSecurity\Exceptions\AccountLockedException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryExpiredException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidStatusException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidTokenException;
use Ae3\AuthSecurity\Exceptions\AuthSecurityException;
use Ae3\AuthSecurity\Exceptions\DuplicateFactorException;
use Ae3\AuthSecurity\Exceptions\InvalidFactorIdentifierException;
use Ae3\AuthSecurity\Exceptions\LastFactorRemovalException;
use Ae3\AuthSecurity\Exceptions\OtpExpiredException;
use Ae3\AuthSecurity\Exceptions\OtpInvalidException;
use Ae3\AuthSecurity\Exceptions\OtpResendLimitException;
use Ae3\AuthSecurity\Exceptions\OtpResendTooSoonException;
use Ae3\AuthSecurity\Exceptions\PasswordPolicyException;
use Ae3\AuthSecurity\Exceptions\PolicyBelowFloorException;
use Ae3\AuthSecurity\Exceptions\RecoveryCodeInvalidException;
use Ae3\AuthSecurity\Exceptions\TemporarilyThrottledException;
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
        $this->bootCommands();
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PurgeExpiredDataCommand::class]);
        }
    }

    /**
     * Registra as rotas do pacote sob um prefixo configurável. Chamar no routes/api.php (ou
     * routes/web.php, para guards de sessão) da app.
     *
     * @param  ?string  $guard  Guard de autenticação (ex.: 'sanctum', 'api' para Passport, 'web' para
     *                          sessão). Passe null para não aplicar nenhum guard automaticamente — útil
     *                          quando o middleware de autenticação já vem via $middleware.
     */
    public static function routes(?string $prefix = null, array $middleware = [], ?string $guard = null): void
    {
        $prefix = $prefix ?? config('auth-security.routes.prefix');
        $guard = $guard ?? config('auth-security.routes.guard');

        $statefulGroup = self::resolveStatefulMiddlewareGroup($guard);
        $baseMiddleware = $guard !== null ? [$statefulGroup, "auth:{$guard}"] : [$statefulGroup];

        Route::prefix($prefix)
            ->middleware(array_merge($baseMiddleware, $middleware))
            ->group(__DIR__.'/Http/routes.php');
    }

    /**
     * Guards com driver 'session' (ex.: 'web') precisam do grupo 'web' pra ter a sessão
     * iniciada — sem isso, auth:{guard} nunca decodifica o cookie e o usuário some. Qualquer
     * outro driver (token, passport, sanctum stateless, etc.) continua em 'api'.
     */
    private static function resolveStatefulMiddlewareGroup(?string $guard): string
    {
        if ($guard === null) {
            return 'api';
        }

        return config("auth.guards.{$guard}.driver") === 'session' ? 'web' : 'api';
    }

    private function registerContracts(): void
    {
        // Bind implementações explicitamente configuradas pela app consumidora
        foreach (self::CONTRACT_MAP as $configKey => $contractInterface) {
            $implementation = config("auth-security.{$configKey}");

            if ($implementation !== null) {
                $this->app->singleton($contractInterface, $implementation);
            }
        }

        // Defaults no-op para contratos não configurados (bindIf preserva bindings acima)
        $this->app->bindIf(MfaAuditLogger::class, NullMfaAuditLogger::class);
        $this->app->bindIf(MfaMessageSender::class, NullMfaMessageSender::class);
        $this->app->bindIf(MfaTenantResolver::class, NullMfaTenantResolver::class);
        $this->app->bindIf(MfaRoleResolver::class, NullMfaRoleResolver::class);
        $this->app->bindIf(MfaContextResolver::class, NullMfaContextResolver::class);
    }

    private function bootPublishes(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'auth-security');

        $this->publishes([
            __DIR__.'/../config/auth-security.php' => config_path('auth-security.php'),
        ], 'auth-security-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'auth-security-migrations');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath(),
        ], 'auth-security-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/auth-security'),
        ], 'auth-security-views');
    }

    private function bootEventListeners(): void
    {
        Event::subscribe(DispatchAuditLogListener::class);
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
            return Limit::perMinute(config('auth-security.rate_limits.verify_per_minute'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:send-otp', function (Request $request) {
            return Limit::perMinute(config('auth-security.rate_limits.send_otp_per_minute'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:generate-recovery', function (Request $request) {
            return Limit::perMinute(config('auth-security.rate_limits.generate_recovery_per_minute'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-security:assisted-recovery', function (Request $request) {
            return Limit::perMinute(config('auth-security.rate_limits.assisted_recovery_per_minute'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
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
        $handler = $this->app->make('Illuminate\Contracts\Debug\ExceptionHandler');

        // Alguns adapters (ex.: Collision, dependendo da versão) não expõem renderable().
        // Sem essa guarda, o boot do provider quebra em apps que os usam.
        if (! method_exists($handler, 'renderable')) {
            return;
        }

        $handler->renderable(function (AuthSecurityException $exception, Request $request) {
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
            $exception instanceof AccountLockedException => [Response::HTTP_LOCKED, ErrorCode::ACCOUNT_LOCKED->value, array_filter(['locked_at' => $exception->getLockedAt()?->toIso8601String()])],
            $exception instanceof TemporarilyThrottledException => [Response::HTTP_TOO_MANY_REQUESTS, ErrorCode::ACCOUNT_THROTTLED->value, ['retry_after_seconds' => now()->diffInSeconds($exception->getRetryAfter())]],
            $exception instanceof OtpExpiredException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::INVALID_CODE->value, []],
            $exception instanceof OtpInvalidException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::INVALID_CODE->value, ['remaining_attempts' => $exception->getRemainingAttempts()]],
            $exception instanceof OtpResendLimitException => [Response::HTTP_TOO_MANY_REQUESTS, ErrorCode::RESEND_RATE_LIMITED->value, []],
            $exception instanceof OtpResendTooSoonException => [Response::HTTP_TOO_MANY_REQUESTS, ErrorCode::RESEND_RATE_LIMITED->value, ['retry_after_seconds' => $exception->getSecondsRemaining()]],
            $exception instanceof RecoveryCodeInvalidException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::INVALID_CODE->value, []],
            $exception instanceof PasswordPolicyException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::WEAK_PASSWORD->value, ['violations' => $exception->getViolations()]],
            $exception instanceof PolicyBelowFloorException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::BELOW_FLOOR->value, ['conflicts' => $exception->getConflicts()]],
            $exception instanceof InvalidFactorIdentifierException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::INVALID_IDENTIFIER->value, []],
            $exception instanceof DuplicateFactorException => [Response::HTTP_CONFLICT, ErrorCode::DUPLICATE_FACTOR->value, []],
            $exception instanceof LastFactorRemovalException => [Response::HTTP_CONFLICT, ErrorCode::LAST_FACTOR_REQUIRED->value, []],
            $exception instanceof AssistedRecoveryInvalidStatusException => [Response::HTTP_CONFLICT, ErrorCode::INVALID_STATUS->value, []],
            $exception instanceof AssistedRecoveryInvalidTokenException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::INVALID_TOKEN->value, []],
            $exception instanceof AssistedRecoveryExpiredException => [Response::HTTP_UNPROCESSABLE_ENTITY, ErrorCode::TOKEN_EXPIRED->value, []],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, ErrorCode::AUTH_SECURITY_ERROR->value, []],
        };
    }
}
