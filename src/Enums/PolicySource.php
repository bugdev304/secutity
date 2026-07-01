<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum PolicySource: string
{
    case POLICY = 'policy';
    case INHERITED = 'inherited';
}
