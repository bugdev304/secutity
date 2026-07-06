<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Unit;

use Ae3\AuthSecurity\AuthSecurityServiceProvider;
use Ae3\AuthSecurity\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class AuthSecurityServiceProviderTest extends TestCase
{
    public function test_routes_uses_web_middleware_group_for_session_guard(): void
    {
        config(['auth.guards.web' => ['driver' => 'session', 'provider' => 'users']]);

        AuthSecurityServiceProvider::routes('routes-provider-test-web', [], 'web');

        $route = $this->findRoute('routes-provider-test-web/mfa/state');

        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth:web', $route->middleware());
        $this->assertNotContains('api', $route->middleware());
    }

    public function test_routes_keeps_api_middleware_group_for_token_based_guard(): void
    {
        config(['auth.guards.sanctum' => ['driver' => 'sanctum', 'provider' => 'users']]);

        AuthSecurityServiceProvider::routes('routes-provider-test-sanctum', [], 'sanctum');

        $route = $this->findRoute('routes-provider-test-sanctum/mfa/state');

        $this->assertContains('api', $route->middleware());
        $this->assertContains('auth:sanctum', $route->middleware());
        $this->assertNotContains('web', $route->middleware());
    }

    public function test_routes_defaults_to_api_when_guard_is_null(): void
    {
        AuthSecurityServiceProvider::routes('routes-provider-test-no-guard', [], null);

        $route = $this->findRoute('routes-provider-test-no-guard/mfa/state');

        $this->assertContains('api', $route->middleware());
    }

    private function findRoute(string $uri): \Illuminate\Routing\Route
    {
        $route = Route::getRoutes()->get('GET')[$uri] ?? null;

        $this->assertNotNull($route, "Rota GET {$uri} não foi registrada.");

        return $route;
    }
}
