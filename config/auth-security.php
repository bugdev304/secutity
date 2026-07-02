<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Schema e modelo de usuário
    |--------------------------------------------------------------------------
    */

    'schema' => '',
    'user_model' => null, // ex.: App\Models\User::class — a app consumidora preenche

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
        'min_length' => 8,
        'classes_required' => 3,  // das 4 classes: maiúscula, minúscula, número, especial
        'common_blacklist' => true,
        'history_size' => 3,
        'expiration_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | MFA — parâmetros operacionais (TEC-01)
    |--------------------------------------------------------------------------
    */

    'mfa' => [
        'otp_validity_minutes' => 10,
        'otp_length' => 6,
        'otp_resend_limit' => 3,
        'otp_resend_interval_seconds' => 30,
        'recovery_codes_count' => 8,
        'recovery_code_format' => '4-4-4-alpha',
        'totp_algorithm' => 'sha1',
        'totp_issuer' => env('APP_NAME', 'App'), // nome exibido no aplicativo autenticador
    ],

    /*
    |--------------------------------------------------------------------------
    | Bloqueio de conta (TEC-04)
    |--------------------------------------------------------------------------
    */

    'lockout' => [
        'max_attempts' => 5,
        'window_minutes' => 10,
        'unlock_strategy' => 'admin_only',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recuperação assistida (TEC-10)
    |--------------------------------------------------------------------------
    */

    'assisted_recovery' => [
        'token_expires_hours' => 24,
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
        'prefix' => 'auth-security',
        'guard' => 'sanctum', // ex.: 'api' para Passport, ou null para não aplicar guard automaticamente
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'driver' => null,        // null = usa o driver default da app
        'key_prefix' => 'auth_security:',
        'policy_ttl_minutes' => 5, // TTL do cache de políticas efetivas
    ],

];
