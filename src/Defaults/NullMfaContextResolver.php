<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Defaults;

use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Illuminate\Http\Request;

/**
 * Implementação padrão do MfaContextResolver: sem contexto de acesso.
 * Consequência: políticas com contexto específico nunca são ativadas — só políticas genéricas.
 * Configure context_resolver em config/auth-security.php para apps com múltiplos contextos.
 */
class NullMfaContextResolver implements MfaContextResolver
{
    public function contextOf(Request $request): ?string
    {
        return null;
    }
}
