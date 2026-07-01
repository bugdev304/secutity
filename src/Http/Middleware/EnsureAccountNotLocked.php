<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Middleware;

use Ae3\AuthSecurity\Services\LockoutService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountNotLocked
{
    public function __construct(
        private readonly LockoutService $lockoutService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->lockoutService->isLocked($user)) {
            return response()->json([
                'message' => __('auth-security.account_locked'),
                'code' => 'ACCOUNT_LOCKED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
