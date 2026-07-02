<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Illuminate\Database\Eloquent\Builder;

class OrganizationPolicy extends AuthSecurityModel
{
    protected $table = 'organization_policies';

    protected $fillable = [
        'tenant_type',
        'tenant_id',
        'role_type',
        'role_id',
        'context',
        'requires_mfa',
        'updated_by_user_id',
    ];

    protected $casts = [
        'requires_mfa' => 'boolean',
    ];

    public function scopeForTenant(Builder $query, string $tenantType, int|string $tenantId): Builder
    {
        return $query->where('tenant_type', $tenantType)->where('tenant_id', $tenantId);
    }

    public function scopeForContext(Builder $query, ?string $context): Builder
    {
        return $query->where(function (Builder $contextQuery) use ($context) {
            $contextQuery->whereNull('context');
            if ($context !== null) {
                $contextQuery->orWhere('context', $context);
            }
        });
    }
}
