<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

class FloorPolicyService
{
    /**
     * Retorna true se o roleType está na lista de papéis que sempre exigem MFA
     * (config floor_policy.roles_required).
     */
    public function requiresMfa(string $roleType): bool
    {
        $requiredRoles = config('auth-security.floor_policy.roles_required', []);

        return in_array($roleType, $requiredRoles, strict: true);
    }

    /**
     * Lança exceção se a política proposta estiver abaixo do piso.
     * Retorna a lista de papéis em conflito (vazia = sem conflito).
     *
     * @param  array<string>  $proposedDisabledRoles  papéis nos quais a política propõe requires_mfa=false
     * @return array<string> lista de papéis em conflito com o piso
     */
    public function findConflicts(array $proposedDisabledRoles): array
    {
        $requiredRoles = config('auth-security.floor_policy.roles_required', []);

        return array_values(array_intersect($proposedDisabledRoles, $requiredRoles));
    }
}
