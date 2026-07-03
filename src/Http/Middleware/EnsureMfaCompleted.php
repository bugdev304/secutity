<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Middleware;

use Ae3\AuthSecurity\Services\MfaRequirementResolver;
use Ae3\AuthSecurity\Services\MfaSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaCompleted
{
    public function __construct(
        private readonly MfaSessionService $mfaSessionService,
        private readonly MfaRequirementResolver $mfaRequirementResolver,
    ) {}

    /**
     * $context vem da definição da rota: middleware('auth-security.mfa:admin').
     * Quando omitido, qualquer usuário autenticado precisa do token MFA.
     */
    public function handle(Request $request, Closure $next, ?string $context = null): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $this->mfaRequirementResolver->isRequiredFor($user, $context)) {
            return $next($request);
        }

        $mfaToken = $request->header('X-Mfa-Session-Token');

        if ($mfaToken === null) {
            return $this->denyMfa();
        }

        $sessionUserId = $this->mfaSessionService->getUserId($mfaToken);

        if ($sessionUserId === null || (string) $sessionUserId !== (string) $user->getAuthIdentifier()) {
            return $this->denyMfa();
        }

        return $next($request);
    }

    private function denyMfa(): Response
    {
        return response()->json([
            'message' => __('auth-security.mfa_required'),
            'code' => 'MFA_REQUIRED',
        ], Response::HTTP_FORBIDDEN);
    }
}
