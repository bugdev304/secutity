<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Mfa\SendOtpAction;
use Ae3\AuthSecurity\Actions\Mfa\VerifyMfaFactorAction;
use Ae3\AuthSecurity\Actions\Mfa\VerifyRecoveryCodeAction;
use Ae3\AuthSecurity\Enums\ErrorCode;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Http\Requests\VerifyMfaRequest;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Ae3\AuthSecurity\Support\IdentifierMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class MfaVerificationController extends Controller
{
    /** POST /mfa/factors/{factor}/challenge */
    public function challenge(
        Request $request,
        Factor $factor,
        SendOtpAction $sendOtp,
    ): JsonResponse {
        abort_if(
            (string) $factor->user_id !== (string) $request->user()->getAuthIdentifier(),
            Response::HTTP_NOT_FOUND,
        );

        $factorType = $factor->type;

        if ($factorType->isOtp()) {
            $sendOtp->execute($request->user(), $factor);

            return response()->json([
                'data' => [
                    'challenge_id' => (string) $factor->id,
                    'channel' => $factorType->value,
                    'masked_identifier' => IdentifierMasker::mask($factor->identifier),
                    'expires_at' => now()->addMinutes(config('auth-security.mfa.otp_validity_minutes'))->toIso8601String(),
                    'resend_available_at' => now()->addSeconds(config('auth-security.mfa.otp_resend_interval_seconds'))->toIso8601String(),
                ],
                'meta' => [],
            ]);
        }

        // TOTP ou recovery_code: não envia, só instrui
        $instruction = match ($factorType) {
            FactorType::AUTHENTICATOR_APP => __('auth-security::auth-security.totp_instruction', [], app()->getLocale()) ?: 'Open your authenticator app and enter the current code generated for this account.',
            default => __('auth-security::auth-security.recovery_code_instruction', [], app()->getLocale()) ?: 'Use one of your unused recovery codes.',
        };

        return response()->json([
            'data' => [
                'challenge_id' => (string) $factor->id,
                'channel' => $factorType->value,
                'instruction' => $instruction,
            ],
            'meta' => [],
        ]);
    }

    /** POST /mfa/verify */
    public function verify(
        VerifyMfaRequest $request,
        VerifyMfaFactorAction $verifyMfaFactor,
    ): JsonResponse {
        $sessionData = $verifyMfaFactor->execute(
            $request->user(),
            $request->integer('factor_id'),
            FactorType::from($request->input('factor_type')),
            $request->input('code'),
        );

        return response()->json([
            'data' => $sessionData,
            'meta' => [],
        ]);
    }

    /** POST /mfa/factors/{factor}/challenge/resend */
    public function resend(
        Request $request,
        Factor $factor,
        SendOtpAction $sendOtp,
    ): JsonResponse {
        abort_if(
            (string) $factor->user_id !== (string) $request->user()->getAuthIdentifier(),
            Response::HTTP_NOT_FOUND,
        );

        if (! $factor->type->isOtp()) {
            return response()->json([
                'message' => 'Resend is not available for this factor type.',
                'code' => ErrorCode::RESEND_NOT_ALLOWED->value,
            ], Response::HTTP_BAD_REQUEST);
        }

        $sendOtp->execute($request->user(), $factor);

        return response()->json([
            'data' => [
                'resent' => true,
                'channel' => $factor->type->value,
                'masked_identifier' => IdentifierMasker::mask($factor->identifier),
            ],
            'meta' => [],
        ]);
    }

    /** POST /mfa/recovery-codes/verify */
    public function verifyRecovery(
        Request $request,
        VerifyRecoveryCodeAction $verifyRecoveryCode,
        MfaSessionService $mfaSessionService,
    ): JsonResponse {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        $verifyRecoveryCode->execute($user, $request->input('code'));

        $sessionData = $mfaSessionService->create($user);

        return response()->json([
            'data' => $sessionData,
            'meta' => [],
        ]);
    }
}
