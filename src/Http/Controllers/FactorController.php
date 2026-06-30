<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Mfa\ConfirmFactorEnrollmentAction;
use Ae3\AuthSecurity\Actions\Mfa\EnrollOtpFactorAction;
use Ae3\AuthSecurity\Actions\Mfa\EnrollTotpFactorAction;
use Ae3\AuthSecurity\Actions\Mfa\ListUserFactorsAction;
use Ae3\AuthSecurity\Actions\Mfa\RemoveFactorAction;
use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Ae3\AuthSecurity\Contracts\MfaRoleResolver;
use Ae3\AuthSecurity\Contracts\MfaTenantResolver;
use Ae3\AuthSecurity\Enums\FactorType;
use Ae3\AuthSecurity\Exceptions\InvalidFactorIdentifierException;
use Ae3\AuthSecurity\Http\Requests\ConfirmFactorRequest;
use Ae3\AuthSecurity\Http\Requests\EnrollFactorRequest;
use Ae3\AuthSecurity\Http\Resources\FactorResource;
use Ae3\AuthSecurity\Models\Factor;
use Ae3\AuthSecurity\Support\ContactTokenizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class FactorController extends Controller
{
    public function index(
        Request $request,
        ListUserFactorsAction $listFactors,
    ): AnonymousResourceCollection {
        $factors = $listFactors->execute($request->user());

        return FactorResource::collection($factors)->additional(['meta' => []]);
    }

    public function store(
        EnrollFactorRequest $request,
        EnrollOtpFactorAction $enrollOtp,
        EnrollTotpFactorAction $enrollTotp,
    ): JsonResponse {
        $user = $request->user();
        $factorType = FactorType::from($request->input('type'));

        if ($factorType === FactorType::AuthenticatorApp) {
            $registrationData = $enrollTotp->execute(
                $user,
                $request->input('holder_name'),
                $request->input('name'),
            );

            return response()->json([
                'data' => [
                    'factor_id' => $registrationData->factorId,
                    'secret' => $registrationData->plainSecret,
                    'otpauth_uri' => $registrationData->qrCodeUri,
                    'qr_code_svg' => $registrationData->qrCodeInline,
                ],
                'meta' => [],
            ], Response::HTTP_CREATED);
        }

        $contact = ContactTokenizer::resolve($user, $request->input('contact_token'));

        if ($contact === null) {
            throw new InvalidFactorIdentifierException;
        }

        $factor = $enrollOtp->execute(
            $user,
            $factorType,
            $contact->identifier,
            $request->input('name'),
        );

        return response()->json([
            'data' => new FactorResource($factor),
            'meta' => ['enrollment_started' => true],
        ], Response::HTTP_CREATED);
    }

    public function confirm(
        ConfirmFactorRequest $request,
        Factor $factor,
        ConfirmFactorEnrollmentAction $confirmEnrollment,
    ): JsonResponse {
        $confirmedFactor = $confirmEnrollment->execute(
            $request->user(),
            $factor,
            $request->input('code'),
        );

        return response()->json([
            'data' => new FactorResource($confirmedFactor),
            'meta' => [],
        ]);
    }

    public function destroy(
        Request $request,
        Factor $factor,
        RemoveFactorAction $removeFactor,
        MfaTenantResolver $tenantResolver,
        MfaRoleResolver $roleResolver,
        MfaContextResolver $contextResolver,
    ): JsonResponse {
        $user = $request->user();
        $tenant = $tenantResolver->tenantOf($user);
        $roles = $roleResolver->rolesOf($user);
        $context = $contextResolver->contextOf($request);

        $mfaRequired = $tenant !== null && collect($roles)->contains(
            fn (string $role) => $roleResolver->requiresMfa($tenant, $role, $context)
        );

        $removeFactor->execute($user, $factor, $mfaRequired);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function alternatives(
        Request $request,
        ListUserFactorsAction $listFactors,
    ): JsonResponse {
        $factors = $listFactors->execute($request->user())
            ->where('id', '!=', $request->query('exclude_factor_id'));

        return response()->json([
            'data' => [
                'factors' => FactorResource::collection($factors),
                'assisted_recovery_available' => true,
            ],
            'meta' => [],
        ]);
    }
}
