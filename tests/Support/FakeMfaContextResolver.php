<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaContextResolver;
use Illuminate\Http\Request;

class FakeMfaContextResolver implements MfaContextResolver
{
    public function contextOf(Request $request): ?string
    {
        return null;
    }
}
