<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Support;

use Ae3\AuthSecurity\Concerns\HasAuthSecurity;
use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Enums\MfaChannel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class TestUser extends Authenticatable implements MfaContactProvider
{
    use HasApiTokens;
    use HasAuthSecurity;

    protected $table = 'users';

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password'];

    public function mfaContacts(): array
    {
        return [
            new MfaContact(channel: MfaChannel::EMAIL, identifier: $this->email, label: 'E-mail'),
        ];
    }
}
