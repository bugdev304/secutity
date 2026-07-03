<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Policy;

use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Ae3\AuthSecurity\Services\FloorPolicyService;
use Illuminate\Support\Facades\Cache;

class GetEffectivePolicyAction
{
    public function __construct(
        private readonly FloorPolicyService $floorPolicyService,
    ) {}

    /**
     * Retorna se MFA é obrigatório para o par (tenant, role, context).
     *
     * Precedência:
     * 1. Piso comum (floor_policy) → se o papel está no piso, sempre true.
     * 2. Política específica com contexto (context IS NOT NULL).
     * 3. Política genérica sem contexto (context IS NULL) — herança.
     * 4. Default false (sem política configurada = MFA não exigido).
     *
     * Resultado cacheado por TTL configurável para evitar N consultas por request.
     */
    public function execute(
        string $tenantType,
        int|string $tenantId,
        string $roleType,
        int|string $roleId,
        ?string $context = null,
    ): bool {
        if ($this->floorPolicyService->requiresMfa($roleType)) {
            return true;
        }

        $cacheDriver = config('auth-security.cache.driver');
        $prefix = config('auth-security.cache.key_prefix');
        $ttlMinutes = config('auth-security.cache.policy_ttl_minutes');
        $cacheKey = "{$prefix}policy:{$tenantType}:{$tenantId}:{$roleType}:{$roleId}:{$context}";

        return Cache::store($cacheDriver)->remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            function () use ($tenantType, $tenantId, $roleType, $roleId, $context): bool {
                $policy = OrganizationPolicy::forTenant($tenantType, $tenantId)
                    ->where('role_type', $roleType)
                    ->where('role_id', $roleId)
                    ->forContext($context)
                    ->orderByRaw('context IS NULL') // contexto específico tem precedência (context NOT NULL vem primeiro)
                    ->first();

                return $policy?->requires_mfa ?? false;
            },
        );
    }
}
