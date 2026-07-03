<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Controllers;

use Ae3\AuthSecurity\Actions\Account\UnlockAccountAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    /** POST /accounts/{userId}/unlock */
    public function unlock(
        Request $request,
        int $userId,
        UnlockAccountAction $unlockAccount,
    ): JsonResponse {
        $unlockAccount->execute($userId, $request->user());

        return response()->json([
            'data' => ['unlocked' => true],
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
