<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum AssistedRecoveryReason: string
{
    case DeviceLost = 'device_lost';
    case RecoveryCodesLost = 'recovery_codes_lost';
    case DeviceChange = 'device_change';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            AssistedRecoveryReason::DeviceLost => 'Dispositivo perdido ou roubado',
            AssistedRecoveryReason::RecoveryCodesLost => 'Códigos de recuperação perdidos',
            AssistedRecoveryReason::DeviceChange => 'Troca de dispositivo',
            AssistedRecoveryReason::Other => 'Outro motivo',
        };
    }

    public function requiresManualDescription(): bool
    {
        return match ($this) {
            AssistedRecoveryReason::Other => true,
            AssistedRecoveryReason::DeviceLost,
            AssistedRecoveryReason::RecoveryCodesLost,
            AssistedRecoveryReason::DeviceChange => false,
        };
    }
}
