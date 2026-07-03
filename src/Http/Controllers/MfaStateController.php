<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Mfa\ResolveMfaStateAction;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Http\Resources\FactorResource;
use Ae3\AuthSecurity\Http\Resources\MfaContactResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MfaStateController extends Controller
{
    /** GET /mfa/state */
    public function show(
        Request $request,
        ResolveMfaStateAction $resolveMfaState,
        MfaContextResolver $contextResolver,
    ): JsonResponse {
        $user = $request->user();

        $state = $resolveMfaState->execute(
            $user,
            $request->header('X-Mfa-Session-Token'),
            $contextResolver->contextOf($request),
        );

        return response()->json([
            'data' => [
                'must_register_factor' => $state['must_register_factor'],
                'mfa_required' => $state['mfa_required'],
                'mfa_satisfied' => $state['mfa_satisfied'],
                'password_expired' => $state['password_expired'],
                'account_locked' => $state['account_locked'],
                'factors' => FactorResource::collection($state['factors']),
                'contacts' => MfaContactResource::collection($state['contacts']),
            ],
            'meta' => [],
        ]);
    }
}
