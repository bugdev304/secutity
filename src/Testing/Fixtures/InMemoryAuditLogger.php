<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Testing\Fixtures;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;

/**
 * Fixture de teste: acumula eventos de auditoria em memória.
 * Permite assertions em testes: assertamos que eventos corretos foram disparados.
 *
 * Exemplo de uso em testes:
 *   $logger = $this->app->make(MfaAuditLogger::class);
 *   $this->assertTrue($logger->hasEvent('mfa.factor.enrolled'));
 *   $this->assertCount(1, $logger->getLogs());
 */
class InMemoryAuditLogger implements MfaAuditLogger
{
    private array $logs = [];

    public function logEvent(string $event, array $payload): void
    {
        $this->logs[] = ['event' => $event, 'payload' => $payload];
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getLogsForEvent(string $event): array
    {
        return array_values(
            array_filter($this->logs, fn (array $log) => $log['event'] === $event),
        );
    }

    public function hasEvent(string $event): bool
    {
        foreach ($this->logs as $log) {
            if ($log['event'] === $event) {
                return true;
            }
        }

        return false;
    }

    public function reset(): void
    {
        $this->logs = [];
    }
}
