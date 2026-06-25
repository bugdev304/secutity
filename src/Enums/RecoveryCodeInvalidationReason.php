<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum RecoveryCodeInvalidationReason: string
{
    case Used = 'used';
    case Regenerated = 'regenerated';

    public function label(): string
    {
        return match ($this) {
            RecoveryCodeInvalidationReason::Used => 'Consumido na verificação',
            RecoveryCodeInvalidationReason::Regenerated => 'Invalidado por nova leva de códigos',
        };
    }
}
