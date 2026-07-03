<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Decide se a verificação em duas etapas é obrigatória para um usuário num contexto
 * de acesso. Usado por EnsureMfaCompleted (bloqueio) e por ResolveMfaStateAction
 * (discovery) — extraído pra não duplicar a mesma regra nos dois lugares.
 */
class MfaRequirementResolver
{
    public function __construct(
        private readonly MfaTenantResolver $tenantResolver,
        private readonly MfaRoleResolver $roleResolver,
    ) {}

    public function isRequiredFor(Authenticatable $user, ?string $context = null): bool
    {
        $tenant = $this->tenantResolver->tenantOf($user);

        // Sem tenant resolvido: sem RBAC configurado — exige MFA para todos.
        if ($tenant === null) {
            return true;
        }

        $roles = $this->roleResolver->rolesOf($user);

        return collect($roles)->contains(
            fn (string $role) => $this->roleResolver->requiresMfa($tenant, $role, $context)
        );
    }
}
