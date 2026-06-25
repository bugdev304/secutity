<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Policy\GetEffectivePolicyAction;
use Ae3\AuthSecurity\Actions\Policy\UpsertOrganizationPolicyAction;
use Ae3\AuthSecurity\Http\Requests\UpsertOrganizationPolicyRequest;
use Ae3\AuthSecurity\Http\Resources\OrganizationPolicyResource;
use Ae3\AuthSecurity\Models\OrganizationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrganizationPolicyController extends Controller
{
    /** GET /organization-policies?tenant_type=&tenant_id= */
    public function index(Request $request, GetEffectivePolicyAction $getPolicy): JsonResponse
    {
        $request->validate([
            'tenant_type' => ['required', 'string'],
            'tenant_id' => ['required', 'integer'],
        ]);

        $policies = OrganizationPolicy::forTenant(
            $request->input('tenant_type'),
            $request->input('tenant_id'),
        )->get();

        return response()->json([
            'data' => OrganizationPolicyResource::collection($policies),
            'meta' => [],
        ]);
    }

    /** PUT /organization-policies */
    public function upsert(
        UpsertOrganizationPolicyRequest $request,
        UpsertOrganizationPolicyAction $upsertPolicy,
    ): JsonResponse {
        $policy = $upsertPolicy->execute(
            tenantType: $request->input('tenant_type'),
            tenantId: $request->input('tenant_id'),
            roleType: $request->input('role_type'),
            roleId: $request->input('role_id'),
            requiresMfa: $request->boolean('requires_mfa'),
            context: $request->input('context'),
            updatedBy: $request->user(),
        );

        return response()->json([
            'data' => new OrganizationPolicyResource($policy),
            'meta' => [],
        ]);
    }
}
