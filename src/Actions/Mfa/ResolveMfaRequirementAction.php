<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Actions\Policy\GetEffectivePolicyAction;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Determina se a segunda etapa (MFA) é obrigatória para o usuário
 * no contexto de acesso corrente (tenant + papel + contexto).
 *
 * O controller/middleware deve resolver tenant/role/context via os contratos
 * MfaTenantResolver, MfaRoleResolver, MfaContextResolver e passar os valores aqui.
 */
class ResolveMfaRequirementAction
{
    public function __construct(
        private readonly GetEffectivePolicyAction $getEffectivePolicyAction,
    ) {}

    public function execute(
        Authenticatable $user,
        string $tenantType,
        int|string $tenantId,
        string $roleType,
        int|string $roleId,
        ?string $context = null,
    ): bool {
        return $this->getEffectivePolicyAction->execute(
            $tenantType,
            $tenantId,
            $roleType,
            $roleId,
            $context,
        );
    }
}
