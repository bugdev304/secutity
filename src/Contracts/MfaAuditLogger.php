<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

interface MfaAuditLogger
{
    /**
     * Registra um evento de auditoria do pacote na trilha da app consumidora.
     *
     * @param  string  $event  Identificador do evento (ex.: 'mfa.factor.enrolled').
     * @param  array  $payload  Contexto estruturado: user_id, tenant_id, actor_id, reason, etc.
     *                          Nunca incluir secrets ou PII além do necessário para rastreabilidade.
     */
    public function logEvent(string $event, array $payload): void;
}
