<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Facades;

use Ae3\AuthSecurity\AuthSecurity as AuthSecurityEntry;
use Illuminate\Support\Facades\Facade;

/**
 * @see AuthSecurityEntry
 */
class AuthSecurity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AuthSecurityEntry::class;
    }
}
