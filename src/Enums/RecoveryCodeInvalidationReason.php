<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum RecoveryCodeInvalidationReason: string
{
    case USED = 'used';
    case REGENERATED = 'regenerated';

    public function label(): string
    {
        return match ($this) {
            RecoveryCodeInvalidationReason::USED => 'Consumido na verificação',
            RecoveryCodeInvalidationReason::REGENERATED => 'Invalidado por nova leva de códigos',
        };
    }
}
