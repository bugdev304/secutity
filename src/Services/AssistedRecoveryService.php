<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryExpiredException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidStatusException;
use Ae3\AuthSecurity\Exceptions\AssistedRecoveryInvalidTokenException;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\UserState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AssistedRecoveryService
{
    public function request(
        Authenticatable $targetUser,
        AssistedRecoveryReason $reason,
        ?string $reasonText = null,
    ): AssistedRecovery {
        return AssistedRecovery::create([
            'target_user_id' => $targetUser->getAuthIdentifier(),
            'reason_category' => $reason,
            'reason_text' => $reasonText,
            'status' => AssistedRecoveryStatus::Requested,
            'requested_at' => now(),
        ]);
    }

    /**
     * Libera a recuperação: gera token de uso único, armazena hash e retorna o texto plano.
     * Só é possível quando status é Requested ou InAnalysis.
     * Lança AssistedRecoveryInvalidStatusException se o status atual não permite liberação.
     */
    public function release(AssistedRecovery $recovery, Authenticatable $admin): string
    {
        if ($recovery->status->isTerminal() || $recovery->status === AssistedRecoveryStatus::Released) {
            throw new AssistedRecoveryInvalidStatusException($recovery->status);
        }

        $plainToken = Str::random(64);
        $tokenExpiresHours = config('auth-security.assisted_recovery.token_expires_hours', 24);

        $recovery->update([
            'executed_by_user_id' => $admin->getAuthIdentifier(),
            'status' => AssistedRecoveryStatus::Released,
            'recovery_token_hash' => Hash::make($plainToken),
            'token_expires_at' => now()->addHours($tokenExpiresHours),
            'released_at' => now(),
        ]);

        return $plainToken;
    }

    /**
     * Conclui a recuperação: valida o token e marca must_register_factor=true (TEC-11).
     * Lança AssistedRecoveryInvalidStatusException, AssistedRecoveryExpiredException
     * ou AssistedRecoveryInvalidTokenException conforme o erro.
     */
    public function complete(AssistedRecovery $recovery, string $plainToken): void
    {
        if (! $recovery->status->allowsExecution()) {
            throw new AssistedRecoveryInvalidStatusException($recovery->status);
        }

        if ($recovery->isExpired()) {
            throw new AssistedRecoveryExpiredException;
        }

        if ($recovery->recovery_token_hash === null || ! Hash::check($plainToken, $recovery->recovery_token_hash)) {
            throw new AssistedRecoveryInvalidTokenException;
        }

        $recovery->update([
            'status' => AssistedRecoveryStatus::Completed,
            'recovery_token_hash' => null, // invalida o token após uso
            'completed_at' => now(),
        ]);

        // TEC-11: força o cadastro de novo fator antes de liberar o acesso normal
        UserState::updateOrCreate(
            ['user_id' => $recovery->target_user_id],
            ['must_register_factor' => true],
        );

        $this->revokeTokens($recovery->target_user_id);
    }

    /**
     * Recusa a recuperação. Só é possível quando status não é terminal.
     */
    public function refuse(AssistedRecovery $recovery, Authenticatable $admin): void
    {
        if ($recovery->status->isTerminal()) {
            throw new AssistedRecoveryInvalidStatusException($recovery->status);
        }

        $recovery->update([
            'executed_by_user_id' => $admin->getAuthIdentifier(),
            'status' => AssistedRecoveryStatus::Refused,
            'refused_at' => now(),
        ]);

        UserState::updateOrCreate(
            ['user_id' => $recovery->target_user_id],
            ['recovery_refused_at' => now()],
        );

        $this->revokeTokens($recovery->target_user_id);
    }

    private function revokeTokens(int $userId): void
    {
        $userModel = config('auth-security.user_model');
        $userModel::find($userId)?->tokens()->delete();
    }
}
