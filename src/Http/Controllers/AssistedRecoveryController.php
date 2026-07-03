<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\AssistedRecovery\CompleteAssistedRecoveryAction;
use Ae3\AuthSecurity\Actions\AssistedRecovery\RefuseAssistedRecoveryAction;
use Ae3\AuthSecurity\Actions\AssistedRecovery\ReleaseAssistedRecoveryAction;
use Ae3\AuthSecurity\Actions\AssistedRecovery\RequestAssistedRecoveryAction;
use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Http\Requests\CompleteAssistedRecoveryRequest;
use Ae3\AuthSecurity\Http\Requests\RefuseAssistedRecoveryRequest;
use Ae3\AuthSecurity\Http\Requests\RequestAssistedRecoveryRequest;
use Ae3\AuthSecurity\Http\Resources\AssistedRecoveryResource;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class AssistedRecoveryController extends Controller
{
    /** POST /mfa/assisted-recoveries */
    public function store(
        RequestAssistedRecoveryRequest $request,
        RequestAssistedRecoveryAction $requestRecovery,
    ): JsonResponse {
        $recovery = $requestRecovery->execute(
            $request->input('target_user_id'),
            $request->user(),
            AssistedRecoveryReason::from($request->input('reason_category')),
            $request->input('reason_text'),
        );

        return response()->json([
            'data' => new AssistedRecoveryResource($recovery),
            'meta' => [],
        ], Response::HTTP_CREATED);
    }

    /** POST /mfa/assisted-recoveries/{recovery}/release */
    public function release(
        Request $request,
        AssistedRecovery $recovery,
        ReleaseAssistedRecoveryAction $releaseRecovery,
    ): JsonResponse {
        $plainToken = $releaseRecovery->execute($recovery, $request->user());

        return response()->json([
            'data' => array_merge(
                (new AssistedRecoveryResource($recovery->fresh()))->toArray($request),
                ['recovery_token' => $plainToken],
            ),
            'meta' => ['warning' => __('auth-security.assisted_recovery_release')],
        ]);
    }

    /** POST /mfa/assisted-recoveries/complete */
    public function complete(
        CompleteAssistedRecoveryRequest $request,
        CompleteAssistedRecoveryAction $completeRecovery,
    ): JsonResponse {
        $recovery = $completeRecovery->execute($request->user(), $request->input('token'));

        return response()->json([
            'data' => new AssistedRecoveryResource($recovery->fresh()),
            'meta' => ['must_register_factor' => true],
        ]);
    }

    /** POST /mfa/assisted-recoveries/{recovery}/refuse */
    public function refuse(
        RefuseAssistedRecoveryRequest $request,
        AssistedRecovery $recovery,
        RefuseAssistedRecoveryAction $refuseRecovery,
    ): JsonResponse {
        $refuseRecovery->execute($recovery, $request->user(), $request->input('reason_text'));

        return response()->json([
            'data' => new AssistedRecoveryResource($recovery->fresh()),
            'meta' => [],
        ]);
    }
}
