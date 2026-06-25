<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Policy;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Exceptions\PolicyBelowFloorException;
use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Ae3\AuthSecurity\Services\FloorPolicyService;
use Illuminate\Contracts\Auth\Authenticatable;

class UpsertOrganizationPolicyAction
{
    public function __construct(
        private readonly FloorPolicyService $floorPolicyService,
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    /**
     * Cria ou atualiza a política de MFA para um papel de um tenant.
     * Lança PolicyBelowFloorException se requires_mfa=false para papel no piso.
     */
    public function execute(
        string $tenantType,
        int|string $tenantId,
        string $roleType,
        int|string $roleId,
        bool $requiresMfa,
        ?string $context = null,
        ?Authenticatable $updatedBy = null,
    ): OrganizationPolicy {
        if (! $requiresMfa) {
            $conflicts = $this->floorPolicyService->findConflicts([$roleType]);
            if ($conflicts !== []) {
                throw new PolicyBelowFloorException($conflicts);
            }
        }

        $policy = OrganizationPolicy::updateOrCreate(
            [
                'tenant_type' => $tenantType,
                'tenant_id' => $tenantId,
                'role_type' => $roleType,
                'role_id' => $roleId,
                'context' => $context,
            ],
            [
                'requires_mfa' => $requiresMfa,
                'updated_by_user_id' => $updatedBy?->getAuthIdentifier(),
            ],
        );

        $this->auditLogger->logEvent('policy.upserted', [
            'tenant_type' => $tenantType,
            'tenant_id' => $tenantId,
            'role_type' => $roleType,
            'role_id' => $roleId,
            'context' => $context,
            'requires_mfa' => $requiresMfa,
        ]);

        return $policy;
    }
}
