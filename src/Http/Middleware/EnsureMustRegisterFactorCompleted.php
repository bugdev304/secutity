<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Middleware;

use Ae3\AuthSecurity\Models\UserState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia acesso normal quando must_register_factor=true (TEC-11).
 * Só permite rotas de cadastro de fator e logout.
 */
class EnsureMustRegisterFactorCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();

        if ($state !== null && $state->must_register_factor) {
            return response()->json([
                'message' => __('auth-security::auth-security.mfa_factor_registration_required'),
                'code' => 'MFA_FACTOR_REGISTRATION_REQUIRED',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
