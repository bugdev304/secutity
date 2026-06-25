<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Listeners;

use Ae3\AuthSecurity\Contracts\MfaAuditLogger;
use Ae3\AuthSecurity\Events\AssistedRecoveryExecuted;
use Ae3\AuthSecurity\Events\MfaFactorEnrolled;
use Ae3\AuthSecurity\Events\MfaFactorRemoved;
use Ae3\AuthSecurity\Events\OtpFailureExceeded;
use Ae3\AuthSecurity\Events\PolicyConfigurationAttemptedBelowFloor;
use Ae3\AuthSecurity\Events\RecoveryCodesGenerated;
use Illuminate\Events\Dispatcher;

class DispatchAuditLogListener
{
    public function __construct(
        private readonly MfaAuditLogger $auditLogger,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(MfaFactorEnrolled::class, [$this, 'onFactorEnrolled']);
        $events->listen(MfaFactorRemoved::class, [$this, 'onFactorRemoved']);
        $events->listen(RecoveryCodesGenerated::class, [$this, 'onRecoveryCodesGenerated']);
        $events->listen(OtpFailureExceeded::class, [$this, 'onOtpFailureExceeded']);
        $events->listen(AssistedRecoveryExecuted::class, [$this, 'onAssistedRecoveryExecuted']);
        $events->listen(PolicyConfigurationAttemptedBelowFloor::class, [$this, 'onPolicyBelowFloor']);
    }

    public function onFactorEnrolled(MfaFactorEnrolled $event): void
    {
        $this->auditLogger->logEvent('mfa.factor.enrolled', [
            'user_id' => $event->userId,
            'factor_id' => $event->factor->id,
            'factor_type' => $event->factor->type->value,
        ]);
    }

    public function onFactorRemoved(MfaFactorRemoved $event): void
    {
        $this->auditLogger->logEvent('mfa.factor.removed', [
            'user_id' => $event->userId,
            'factor_id' => $event->factorId,
            'factor_type' => $event->factorType,
        ]);
    }

    public function onRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->auditLogger->logEvent('mfa.recovery_codes.generated', [
            'user_id' => $event->userId,
            'generation_id' => $event->generationId,
            'codes_count' => $event->codesCount,
        ]);
    }

    public function onOtpFailureExceeded(OtpFailureExceeded $event): void
    {
        $this->auditLogger->logEvent('mfa.otp.failure_exceeded', [
            'user_id' => $event->userId,
            'factor_id' => $event->factorId,
        ]);
    }

    public function onAssistedRecoveryExecuted(AssistedRecoveryExecuted $event): void
    {
        $this->auditLogger->logEvent('mfa.assisted_recovery.executed', [
            'recovery_id' => $event->recovery->id,
            'target_user_id' => $event->recovery->target_user_id,
            'executed_by_user_id' => $event->executedByUserId,
        ]);
    }

    public function onPolicyBelowFloor(PolicyConfigurationAttemptedBelowFloor $event): void
    {
        $this->auditLogger->logEvent('policy.attempted_below_floor', [
            'user_id' => $event->userId,
            'tenant_type' => $event->tenantType,
            'tenant_id' => $event->tenantId,
            'conflicts' => $event->conflicts,
        ]);
    }
}
