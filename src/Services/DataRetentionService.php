<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\Factor;

class DataRetentionService
{
    /**
     * Remove cadastros de fator nunca confirmados após o prazo configurado.
     * São tentativas de enrollment abandonadas — sem valor de segurança/auditoria,
     * só PII (email/telefone) parada sem finalidade (LGPD Art. 15, I).
     */
    public function purgeStalePendingFactors(): int
    {
        $retentionDays = config('auth-security.retention.pending_factors_days');

        if ($retentionDays === null) {
            return 0;
        }

        return Factor::whereNull('confirmed_at')
            ->where('created_at', '<', now()->subDays((int) $retentionDays))
            ->delete();
    }

    /**
     * Remove recuperações assistidas finalizadas (completed/refused) após o prazo
     * configurado. Desativado por padrão — muitas apps precisam manter essa trilha
     * por obrigação legal (LGPD Art. 16, I).
     */
    public function purgeStaleAssistedRecoveries(): int
    {
        $retentionDays = config('auth-security.retention.assisted_recoveries_days');

        if ($retentionDays === null) {
            return 0;
        }

        $terminalStatuses = array_values(array_filter(
            AssistedRecoveryStatus::cases(),
            fn (AssistedRecoveryStatus $status) => $status->isTerminal(),
        ));

        return AssistedRecovery::whereIn('status', array_map(fn (AssistedRecoveryStatus $status) => $status->value, $terminalStatuses))
            ->where('updated_at', '<', now()->subDays((int) $retentionDays))
            ->delete();
    }
}
