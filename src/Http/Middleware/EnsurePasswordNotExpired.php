<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Middleware;

use Ae3\AuthSecurity\Enums\ErrorCode;
use Ae3\AuthSecurity\Services\PasswordPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordNotExpired
{
    public function __construct(
        private readonly PasswordPolicyService $passwordPolicyService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $this->passwordPolicyService->isExpired($user)) {
            return response()->json([
                'message' => __('auth-security.password_expired'),
                'code' => ErrorCode::PASSWORD_EXPIRED->value,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
