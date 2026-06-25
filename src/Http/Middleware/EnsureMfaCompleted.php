<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Middleware;

use Ae3\AuthSecurity\Services\MfaSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaCompleted
{
    public function __construct(
        private readonly MfaSessionService $mfaSessionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $mfaToken = $request->header('X-Mfa-Session-Token');

        if ($mfaToken === null) {
            return $this->requireMfa();
        }

        $sessionUserId = $this->mfaSessionService->getUserId($mfaToken);

        if ($sessionUserId === null || (string) $sessionUserId !== (string) $user->getAuthIdentifier()) {
            return $this->requireMfa();
        }

        return $next($request);
    }

    private function requireMfa(): Response
    {
        return response()->json([
            'message' => __('auth-security::auth-security.mfa_required'),
            'code' => 'MFA_REQUIRED',
        ], Response::HTTP_FORBIDDEN);
    }
}
