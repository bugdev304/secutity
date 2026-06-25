<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Testing\Fixtures;

use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Illuminate\Http\Request;

/**
 * Fixture de teste: lê o contexto de acesso a partir de um header HTTP.
 * Útil para simular contextos diferentes em testes e no Postman sem modificar o código.
 *
 * Exemplo de uso no Postman:
 *   Header: X-Mfa-Context: web_admin
 *   Header: X-Mfa-Context: citizen
 */
class HeaderContextResolver implements MfaContextResolver
{
    public function __construct(
        private readonly string $headerName = 'X-Mfa-Context',
    ) {}

    public function contextOf(Request $request): ?string
    {
        $context = $request->header($this->headerName);

        return ($context !== null && $context !== '') ? $context : null;
    }
}
