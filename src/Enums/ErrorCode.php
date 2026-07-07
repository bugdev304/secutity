<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

/**
 * Catálogo estável de códigos de erro do pacote. Fonte da verdade única —
 * middlewares, controllers e AuthSecurityServiceProvider::resolveExceptionDetails()
 * usam estes casos em vez de strings soltas, e o front consome o campo `code` das
 * respostas de erro (nunca `message`, que é traduzível) para lógica condicional.
 * Espelhado em openapi.yaml (components/schemas/ErrorCode).
 */
enum ErrorCode: string
{
    case MFA_REQUIRED = 'MFA_REQUIRED';
    case MFA_FACTOR_REGISTRATION_REQUIRED = 'MFA_FACTOR_REGISTRATION_REQUIRED';
    case ACCOUNT_LOCKED = 'ACCOUNT_LOCKED';
    case ACCOUNT_THROTTLED = 'ACCOUNT_THROTTLED';
    case PASSWORD_EXPIRED = 'PASSWORD_EXPIRED';
    case INVALID_CODE = 'INVALID_CODE';
    case RESEND_RATE_LIMITED = 'RESEND_RATE_LIMITED';
    case RESEND_NOT_ALLOWED = 'RESEND_NOT_ALLOWED';
    case WEAK_PASSWORD = 'WEAK_PASSWORD';
    case BELOW_FLOOR = 'BELOW_FLOOR';
    case INVALID_IDENTIFIER = 'INVALID_IDENTIFIER';
    case DUPLICATE_FACTOR = 'DUPLICATE_FACTOR';
    case LAST_FACTOR_REQUIRED = 'LAST_FACTOR_REQUIRED';
    case INVALID_STATUS = 'INVALID_STATUS';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case INVALIDATION_REQUIRED = 'INVALIDATION_REQUIRED';
    case AUTH_SECURITY_ERROR = 'AUTH_SECURITY_ERROR';
}
