<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum MfaChannel: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case AUTHENTICATOR_APP = 'authenticator_app';
}
