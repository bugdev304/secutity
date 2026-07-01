<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum AssistedRecoveryReason: string
{
    case DEVICE_LOST = 'device_lost';
    case RECOVERY_CODES_LOST = 'recovery_codes_lost';
    case DEVICE_CHANGE = 'device_change';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            AssistedRecoveryReason::DEVICE_LOST => 'Dispositivo perdido ou roubado',
            AssistedRecoveryReason::RECOVERY_CODES_LOST => 'Códigos de recuperação perdidos',
            AssistedRecoveryReason::DEVICE_CHANGE => 'Troca de dispositivo',
            AssistedRecoveryReason::OTHER => 'Outro motivo',
        };
    }

    public function requiresManualDescription(): bool
    {
        return match ($this) {
            AssistedRecoveryReason::OTHER => true,
            AssistedRecoveryReason::DEVICE_LOST,
            AssistedRecoveryReason::RECOVERY_CODES_LOST,
            AssistedRecoveryReason::DEVICE_CHANGE => false,
        };
    }
}
