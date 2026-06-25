<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Symfony\Component\HttpFoundation\Response;

class OrganizationPolicyControllerTest extends FeatureTestCase
{
    // ── GET /test-api/organization-policies ─────────────────────────────────

    public function test_index_requires_tenant_params(): void
    {
        $response = $this->getJson('/test-api/organization-policies');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_index_returns_policies_for_tenant(): void
    {
        OrganizationPolicy::create([
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'admin',
            'role_id' => 1,
            'requires_mfa' => true,
            'updated_by_user_id' => $this->user->id,
        ]);
        OrganizationPolicy::create([
            'tenant_type' => 'organization',
            'tenant_id' => 2, // different tenant
            'role_type' => 'admin',
            'role_id' => 1,
            'requires_mfa' => false,
            'updated_by_user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/test-api/organization-policies?tenant_type=organization&tenant_id=1');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant_id', 1)
            ->assertJsonPath('data.0.requires_mfa', true);
    }

    // ── PUT /test-api/organization-policies ──────────────────────────────────

    public function test_upsert_creates_policy(): void
    {
        $response = $this->putJson('/test-api/organization-policies', [
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'manager',
            'role_id' => 5,
            'requires_mfa' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.requires_mfa', true);

        $this->assertDatabaseHas('organization_policies', [
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'manager',
            'role_id' => 5,
            'requires_mfa' => true,
        ]);
    }

    public function test_upsert_updates_existing_policy(): void
    {
        OrganizationPolicy::create([
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'manager',
            'role_id' => 5,
            'requires_mfa' => false,
            'updated_by_user_id' => $this->user->id,
        ]);

        $response = $this->putJson('/test-api/organization-policies', [
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'manager',
            'role_id' => 5,
            'requires_mfa' => true,
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.requires_mfa', true);
    }

    public function test_upsert_rejects_policy_below_floor(): void
    {
        $this->app['config']->set('auth-security.floor_policy.roles_required', ['admin']);

        $response = $this->putJson('/test-api/organization-policies', [
            'tenant_type' => 'organization',
            'tenant_id' => 1,
            'role_type' => 'admin',
            'role_id' => 1,
            'requires_mfa' => false,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('code', 'BELOW_FLOOR')
            ->assertJsonStructure(['conflicts']);
    }
}
