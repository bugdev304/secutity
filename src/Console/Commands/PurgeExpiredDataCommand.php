<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Console\Commands;

use Ae3\AuthSecurity\Services\DataRetentionService;
use Illuminate\Console\Command;

/**
 * Elimina dados pessoais sem mais finalidade de tratamento, conforme os prazos
 * configurados em config('auth-security.retention'). Nada é apagado automaticamente
 * pelo pacote — a app consumidora decide se/quando agendar este comando
 * (LGPD Art. 15/16: término do tratamento e eliminação de dados pessoais).
 */
class PurgeExpiredDataCommand extends Command
{
    protected $signature = 'auth-security:purge-expired-data';

    protected $description = 'Elimina dados de auth-security sem finalidade de tratamento (fatores pendentes expirados, recuperações assistidas finalizadas)';

    public function handle(DataRetentionService $retentionService): int
    {
        $purgedFactors = $retentionService->purgeStalePendingFactors();
        $purgedRecoveries = $retentionService->purgeStaleAssistedRecoveries();

        $this->info("Fatores pendentes eliminados: {$purgedFactors}");
        $this->info("Recuperações assistidas eliminadas: {$purgedRecoveries}");

        return self::SUCCESS;
    }
}
