<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_type' => $this->tenant_type,
            'tenant_id' => $this->tenant_id,
            'role_type' => $this->role_type,
            'role_id' => $this->role_id,
            'context' => $this->context,
            'requires_mfa' => $this->requires_mfa,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
