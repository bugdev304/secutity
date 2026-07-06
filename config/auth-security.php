<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Schema e modelo de usuário
    |--------------------------------------------------------------------------
    */

    'schema' => env('AUTH_SECURITY_SCHEMA', ''),
    'user_model' => env('AUTH_SECURITY_USER_MODEL'), // ex.: App\Models\User::class — a app consumidora preenche

    /*
    |--------------------------------------------------------------------------
    | Contratos (opcionais — cada um tem implementação padrão no-op)
    |--------------------------------------------------------------------------
    | Preencha com o FQCN da sua implementação para sobrescrever o default.
    | Deixe null para usar o comportamento padrão descrito abaixo:
    |
    |  tenant_resolver  → NullMfaTenantResolver  — sem tenant (single-tenant)
    |  role_resolver    → NullMfaRoleResolver    — sem papéis, MFA nunca obrigatório via RBAC
    |  context_resolver → NullMfaContextResolver — sem contexto de acesso
    |  message_sender   → NullMfaMessageSender   — OTP logado como warning, nunca enviado
    |  audit_logger     → NullMfaAuditLogger     — eventos descartados silenciosamente
    */

    'tenant_resolver' => null, // implements Ae3\AuthSecurity\Contracts\MfaTenantResolver
    'role_resolver' => null, // implements Ae3\AuthSecurity\Contracts\MfaRoleResolver
    'context_resolver' => null, // implements Ae3\AuthSecurity\Contracts\MfaContextResolver
    'message_sender' => null, // implements Ae3\AuthSecurity\Contracts\MfaMessageSender
    'audit_logger' => null, // implements Ae3\AuthSecurity\Contracts\MfaAuditLogger

    /*
    |--------------------------------------------------------------------------
    | Política de senha forte (TEC-03)
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => env('AUTH_SECURITY_PASSWORD_MIN_LENGTH', 8),
        'classes_required' => env('AUTH_SECURITY_PASSWORD_CLASSES_REQUIRED', 3),  // das 4 classes: maiúscula, minúscula, número, especial
        'common_blacklist' => env('AUTH_SECURITY_PASSWORD_COMMON_BLACKLIST', true),
        'history_size' => env('AUTH_SECURITY_PASSWORD_HISTORY_SIZE', 3),
        'expiration_days' => env('AUTH_SECURITY_PASSWORD_EXPIRATION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA — parâmetros operacionais (TEC-01)
    |--------------------------------------------------------------------------
    */

    'mfa' => [
        'otp_validity_minutes' => env('AUTH_SECURITY_OTP_VALIDITY_MINUTES', 10),
        'otp_length' => env('AUTH_SECURITY_OTP_LENGTH', 6),
        'otp_max_attempts' => env('AUTH_SECURITY_OTP_MAX_ATTEMPTS', 5),
        'otp_resend_limit' => env('AUTH_SECURITY_OTP_RESEND_LIMIT', 3),
        'otp_resend_interval_seconds' => env('AUTH_SECURITY_OTP_RESEND_INTERVAL_SECONDS', 30),
        'session_ttl_hours' => env('AUTH_SECURITY_SESSION_TTL_HOURS', 8),
        'recovery_codes_count' => env('AUTH_SECURITY_RECOVERY_CODES_COUNT', 8),
        'recovery_code_format' => env('AUTH_SECURITY_RECOVERY_CODE_FORMAT', '4-4-4-alpha'),
        'totp_algorithm' => env('AUTH_SECURITY_TOTP_ALGORITHM', 'sha1'),
        'totp_issuer' => env('AUTH_SECURITY_TOTP_ISSUER', env('APP_NAME', 'App')), // nome exibido no aplicativo autenticador
    ],

    /*
    |--------------------------------------------------------------------------
    | Bloqueio de conta (TEC-04)
    |--------------------------------------------------------------------------
    */

    'lockout' => [
        'max_attempts' => env('AUTH_SECURITY_LOCKOUT_MAX_ATTEMPTS', 5),
        'window_minutes' => env('AUTH_SECURITY_LOCKOUT_WINDOW_MINUTES', 10),
        'unlock_strategy' => env('AUTH_SECURITY_LOCKOUT_UNLOCK_STRATEGY', 'admin_only'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recuperação assistida (TEC-10)
    |--------------------------------------------------------------------------
    */

    'assisted_recovery' => [
        'token_expires_hours' => env('AUTH_SECURITY_ASSISTED_RECOVERY_TOKEN_EXPIRES_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Política de piso (floor policy)
    |--------------------------------------------------------------------------
    | Define quais papéis da app SEMPRE exigem MFA, independente da política
    | da organização. A app consumidora lista os papéis dela aqui.
    */

    'floor_policy' => [
        'roles_required' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotas
    |--------------------------------------------------------------------------
    | Aplicados por AuthSecurityServiceProvider::routes() quando o prefixo/guard
    | não são passados explicitamente na chamada.
    */

    'routes' => [
        'prefix' => env('AUTH_SECURITY_ROUTES_PREFIX', 'auth-security'),
        'guard' => env('AUTH_SECURITY_ROUTES_GUARD', 'sanctum'), // ex.: 'api' para Passport, ou null para não aplicar guard automaticamente
    ],

    /*
    |--------------------------------------------------------------------------
    | Retenção de dados (LGPD Art. 15/16 — término do tratamento e eliminação)
    |--------------------------------------------------------------------------
    | Usados pelo comando `auth-security:purge-expired-data` (agende via
    | scheduler da app consumidora). Nenhuma eliminação é automática — a app
    | decide quando/rodar o comando, conforme sua própria base legal.
    |
    |  pending_factors_days      → cadastro de fator (email/sms/TOTP) nunca
    |                              confirmado, contado a partir de created_at.
    |                              null desativa a eliminação.
    |  assisted_recoveries_days  → recuperação assistida finalizada (completed
    |                              ou refused), contada a partir de updated_at.
    |                              null desativa (recomendado se a app tem
    |                              obrigação legal de manter trilha de
    |                              auditoria — LGPD Art. 16, I).
    */

    'retention' => [
        'pending_factors_days' => env('AUTH_SECURITY_RETENTION_PENDING_FACTORS_DAYS', 7),
        'assisted_recoveries_days' => env('AUTH_SECURITY_RETENTION_ASSISTED_RECOVERIES_DAYS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'driver' => env('AUTH_SECURITY_CACHE_DRIVER'), // null = usa o driver default da app
        'key_prefix' => env('AUTH_SECURITY_CACHE_KEY_PREFIX', 'auth_security:'),
        'policy_ttl_minutes' => env('AUTH_SECURITY_CACHE_POLICY_TTL_MINUTES', 5), // TTL do cache de políticas efetivas
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    | Limites por minuto aplicados pelos limiters registrados em
    | AuthSecurityServiceProvider::bootRateLimiters(), usados pelo middleware
    | throttle:auth-security:* nas rotas sensíveis (routes.php).
    */

    'rate_limits' => [
        'verify_per_minute' => env('AUTH_SECURITY_RATE_LIMIT_VERIFY_PER_MINUTE', 10),
        'send_otp_per_minute' => env('AUTH_SECURITY_RATE_LIMIT_SEND_OTP_PER_MINUTE', 5),
        'generate_recovery_per_minute' => env('AUTH_SECURITY_RATE_LIMIT_GENERATE_RECOVERY_PER_MINUTE', 3),
        'assisted_recovery_per_minute' => env('AUTH_SECURITY_RATE_LIMIT_ASSISTED_RECOVERY_PER_MINUTE', 5),
    ],

];
