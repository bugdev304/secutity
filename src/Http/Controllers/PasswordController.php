<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Password\ChangePasswordAction;
use Ae3\AuthSecurity\Http\Requests\ChangePasswordRequest;
use Ae3\AuthSecurity\Models\UserState;
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
        $user = $request->user();

        $changePassword->execute($user, $request->input('new_password'));

        $passwordChangedAt = UserState::where('user_id', $user->getAuthIdentifier())
            ->value('password_changed_at');

        return response()->json([
            'data' => [
                'changed' => true,
                'password_changed_at' => $passwordChangedAt?->toIso8601String(),
            ],
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
