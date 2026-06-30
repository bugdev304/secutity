<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class TestUser extends Authenticatable implements MfaContactProvider
{
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password'];

    public function mfaContacts(): array
    {
        return [
            new MfaContact(channel: 'email', identifier: $this->email, label: 'E-mail'),
        ];
    }
}
