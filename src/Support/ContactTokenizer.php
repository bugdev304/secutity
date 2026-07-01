<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Support;

use Ae3\AuthSecurity\Contracts\MfaContactProvider;
use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Enums\MfaChannel;
use Illuminate\Contracts\Auth\Authenticatable;

class ContactTokenizer
{
    public static function generate(MfaChannel $channel, ?string $identifier): string
    {
        return hash_hmac('sha256', $channel->value.($identifier ?? ''), config('app.key'));
    }

    public static function resolve(Authenticatable $user, string $token): ?MfaContact
    {
        if (! $user instanceof MfaContactProvider) {
            return null;
        }

        foreach ($user->mfaContacts() as $contact) {
            if (hash_equals(static::generate($contact->channel, $contact->identifier), $token)) {
                return $contact;
            }
        }

        return null;
    }
}
