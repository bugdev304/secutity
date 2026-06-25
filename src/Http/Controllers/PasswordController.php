<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Password\ChangePasswordAction;
use Ae3\AuthSecurity\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class PasswordController extends Controller
{
    /** POST /password */
    public function change(
        ChangePasswordRequest $request,
        ChangePasswordAction $changePassword,
    ): JsonResponse {
        $changePassword->execute($request->user(), $request->input('new_password'));

        return response()->json([
            'data' => ['changed' => true],
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
