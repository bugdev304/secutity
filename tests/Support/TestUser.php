<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password'];
}
