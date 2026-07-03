<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Mfa\GenerateRecoveryCodesAction;
use Ae3\AuthSecurity\Enums\ErrorCode;
use Ae3\AuthSecurity\Http\Requests\GenerateRecoveryCodesRequest;
use Ae3\AuthSecurity\Http\Resources\RecoveryCodeMetaResource;
use Ae3\AuthSecurity\Services\RecoveryCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class RecoveryCodeController extends Controller
{
    /** GET /mfa/recovery-codes — metadados apenas, nunca os códigos */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new RecoveryCodeMetaResource($request->user()),
            'meta' => [],
        ]);
    }

    /** POST /mfa/recovery-codes — gera nova leva */
    public function store(
        GenerateRecoveryCodesRequest $request,
        GenerateRecoveryCodesAction $generateCodes,
        RecoveryCodeService $recoveryCodeService,
    ): JsonResponse {
        $user = $request->user();
        $confirmInvalidation = $request->boolean('confirm_invalidation', false);

        if ($recoveryCodeService->hasUnusedCodes($user) && ! $confirmInvalidation) {
            return response()->json([
                'message' => __('auth-security.invalidation_required'),
                'code' => ErrorCode::INVALIDATION_REQUIRED->value,
            ], Response::HTTP_CONFLICT);
        }

        $plainCodes = $generateCodes->execute($user);

        return response()->json([
            'data' => [
                'codes' => $plainCodes,
            ],
            'meta' => ['warning' => 'Store these codes safely. They will not be shown again.'],
        ], Response::HTTP_CREATED);
    }
}
