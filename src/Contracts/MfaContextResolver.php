<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

use Illuminate\Http\Request;

interface MfaContextResolver
{
    /**
     * Retorna o contexto de acesso da request atual (ex.: 'web_admin', 'citizen'),
     * ou null quando não há contexto específico.
     * A app consumidora determina como extrair o contexto (header, subdomain, etc.).
     */
    public function contextOf(Request $request): ?string;
}
