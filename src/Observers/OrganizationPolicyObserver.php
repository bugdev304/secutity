<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Observers;

use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Illuminate\Support\Facades\Cache;

class OrganizationPolicyObserver
{
    public function saved(OrganizationPolicy $policy): void
    {
        $this->forgetPolicyCache($policy);
    }

    public function deleted(OrganizationPolicy $policy): void
    {
        $this->forgetPolicyCache($policy);
    }

    private function forgetPolicyCache(OrganizationPolicy $policy): void
    {
        $prefix = config('auth-security.cache.key_prefix', 'auth_security:');
        $cacheKey = "{$prefix}policy:{$policy->tenant_type}:{$policy->tenant_id}:{$policy->role_type}:{$policy->role_id}:{$policy->context}";

        Cache::store(config('auth-security.cache.driver'))->forget($cacheKey);
    }
}
