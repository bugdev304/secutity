<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum AssistedRecoveryStatus: string
{
    case REQUESTED = 'requested';
    case IN_ANALYSIS = 'in_analysis';
    case RELEASED = 'released';
    case COMPLETED = 'completed';
    case REFUSED = 'refused';

    public function label(): string
    {
        return match ($this) {
            AssistedRecoveryStatus::REQUESTED => 'Aguardando análise',
            AssistedRecoveryStatus::IN_ANALYSIS => 'Em análise',
            AssistedRecoveryStatus::RELEASED => 'Liberada — aguardando conclusão pelo usuário',
            AssistedRecoveryStatus::COMPLETED => 'Concluída',
            AssistedRecoveryStatus::REFUSED => 'Recusada',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            AssistedRecoveryStatus::COMPLETED,
            AssistedRecoveryStatus::REFUSED => true,
            AssistedRecoveryStatus::REQUESTED,
            AssistedRecoveryStatus::IN_ANALYSIS,
            AssistedRecoveryStatus::RELEASED => false,
        };
    }

    public function allowsExecution(): bool
    {
        return match ($this) {
            AssistedRecoveryStatus::RELEASED => true,
            AssistedRecoveryStatus::REQUESTED,
            AssistedRecoveryStatus::IN_ANALYSIS,
            AssistedRecoveryStatus::COMPLETED,
            AssistedRecoveryStatus::REFUSED => false,
        };
    }
}
