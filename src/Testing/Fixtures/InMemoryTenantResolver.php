<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Testing\Fixtures;

use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Contracts\TenantIdentity;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Fixture de teste: resolve tenant a partir de um mapa em memória.
 * Use em testes automatizados e em apps sandbox para Postman.
 *
 * Exemplo de uso em testes:
 *   $resolver = $this->app->make(MfaTenantResolver::class);
 *   $resolver->setTenantFor($user->id, new SimpleTenant(1, 'App\Models\Company'));
 */
class InMemoryTenantResolver implements MfaTenantResolver
{
    /** @var array<int|string, TenantIdentity> */
    private array $tenants = [];

    public function setTenantFor(int|string $userId, TenantIdentity $tenant): void
    {
        $this->tenants[$userId] = $tenant;
    }

    public function reset(): void
    {
        $this->tenants = [];
    }

    public function tenantOf(Authenticatable $user): ?TenantIdentity
    {
        return $this->tenants[$user->getAuthIdentifier()] ?? null;
    }
}
