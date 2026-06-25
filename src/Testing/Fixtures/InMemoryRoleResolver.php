<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Testing\Fixtures;

use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Fixture de teste: resolve papéis e requisito de MFA a partir de configuração em memória.
 * Use em testes automatizados e em apps sandbox para Postman.
 *
 * Exemplo de uso em testes:
 *   $resolver = $this->app->make(MfaRoleResolver::class);
 *   $resolver->setRoles(['organization_admin']);
 *   $resolver->requireMfa(true);
 */
class InMemoryRoleResolver implements MfaRoleResolver
{
    private array $roles = [];

    private bool $isMfaRequired = false;

    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    public function requireMfa(bool $required = true): void
    {
        $this->isMfaRequired = $required;
    }

    public function reset(): void
    {
        $this->roles = [];
        $this->isMfaRequired = false;
    }

    public function rolesOf(Authenticatable $user): array
    {
        return $this->roles;
    }

    public function requiresMfa(TenantIdentity $tenant, string $role, ?string $context = null): bool
    {
        return $this->isMfaRequired;
    }
}
