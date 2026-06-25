<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Schema e modelo de usuário
    |--------------------------------------------------------------------------
    */

    'schema' => 'auth_security',
    'user_model' => null, // ex.: App\Models\User::class — a app consumidora preenche

    /*
    |--------------------------------------------------------------------------
    | Validação de contratos no boot
    |--------------------------------------------------------------------------
    | Quando true (padrão), o ServiceProvider lança RuntimeException em boot()
    | se algum dos 5 contratos obrigatórios não estiver vinculado no container.
    | Desative apenas em ambiente de teste (o TestCase do pacote faz isso).
    */

    'require_contracts' => true,

    /*
    |--------------------------------------------------------------------------
    | Contratos (implementados pela app consumidora)
    |--------------------------------------------------------------------------
    | O ServiceProvider valida em boot() que todos estão preenchidos.
    | Deixe null apenas enquanto ainda não configurou — o pacote falhará cedo
    | com mensagem clara se algum contrato obrigatório estiver ausente.
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
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'driver' => null, // null = usa o driver default da app
        'key_prefix' => 'auth_security:',
    ],

];
