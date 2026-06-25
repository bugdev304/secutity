<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum AssistedRecoveryStatus: string
{
    case Requested = 'requested';
    case InAnalysis = 'in_analysis';
    case Released = 'released';
    case Completed = 'completed';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            AssistedRecoveryStatus::Requested => 'Aguardando análise',
            AssistedRecoveryStatus::InAnalysis => 'Em análise',
            AssistedRecoveryStatus::Released => 'Liberada — aguardando conclusão pelo usuário',
            AssistedRecoveryStatus::Completed => 'Concluída',
            AssistedRecoveryStatus::Refused => 'Recusada',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            AssistedRecoveryStatus::Completed,
            AssistedRecoveryStatus::Refused => true,
            AssistedRecoveryStatus::Requested,
            AssistedRecoveryStatus::InAnalysis,
            AssistedRecoveryStatus::Released => false,
        };
    }

    public function allowsExecution(): bool
    {
        return match ($this) {
            AssistedRecoveryStatus::Released => true,
            AssistedRecoveryStatus::Requested,
            AssistedRecoveryStatus::InAnalysis,
            AssistedRecoveryStatus::Completed,
            AssistedRecoveryStatus::Refused => false,
        };
    }
}
