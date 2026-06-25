<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Defaults;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;

/**
 * Implementação padrão do MfaAuditLogger: descarta todos os eventos silenciosamente.
 * Substitua por uma implementação real em config/auth-security.php para produção.
 */
class NullMfaAuditLogger implements MfaAuditLogger
{
    public function logEvent(string $event, array $payload): void
    {
        // no-op — configure audit_logger em config/auth-security.php
    }
}
