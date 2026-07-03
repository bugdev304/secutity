<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Enums\RecoveryCodeInvalidationReason;
use Ae3\AuthSecurity\Exceptions\RecoveryCodeInvalidException;
use Ae3\AuthSecurity\Models\RecoveryCode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryCodeService
{
    // Caracteres sem ambiguidade visual: sem 0/O, 1/I/L
    private const CODE_CHARSET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const CODE_GROUP_LENGTH = 4;

    private const CODE_GROUP_COUNT = 3;

    /**
     * Gera nova leva de códigos de recuperação.
     * P2.A: códigos não-usados da leva anterior são hard-deleted.
     * Retorna os códigos em plain text — única vez que são expostos.
     */
    public function generate(Authenticatable $user): array
    {
        $userId = $user->getAuthIdentifier();
        $generationId = Str::uuid()->toString();
        $codesCount = config('auth-security.mfa.recovery_codes_count');

        return DB::transaction(function () use ($userId, $generationId, $codesCount) {
            $this->invalidatePreviousGeneration($userId);

            $plainCodes = [];

            for ($codeIndex = 0; $codeIndex < $codesCount; $codeIndex++) {
                $plainCode = $this->generatePlainCode();
                $plainCodes[] = $plainCode;

                RecoveryCode::create([
                    'user_id' => $userId,
                    'code_hash' => Hash::make($plainCode),
                    'generation_id' => $generationId,
                ]);
            }

            return $plainCodes;
        });
    }

    public function hasUnusedCodes(Authenticatable $user): bool
    {
        return RecoveryCode::where('user_id', $user->getAuthIdentifier())
            ->whereNull('used_at')
            ->exists();
    }

    /**
     * Verifica um código de recuperação e o marca como usado.
     * Lança RecoveryCodeInvalidException se nenhum código disponível bater.
     */
    public function verify(Authenticatable $user, string $code): RecoveryCode
    {
        $availableCodes = RecoveryCode::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->whereNull('used_at')
            ->get();

        foreach ($availableCodes as $recoveryCode) {
            if (Hash::check($code, $recoveryCode->code_hash)) {
                $recoveryCode->update([
                    'used_at' => now(),
                    'invalidation_reason' => RecoveryCodeInvalidationReason::USED,
                ]);

                return $recoveryCode;
            }
        }

        throw new RecoveryCodeInvalidException;
    }

    private function invalidatePreviousGeneration(int|string $userId): void
    {
        $latestGenerationId = RecoveryCode::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->value('generation_id');

        if ($latestGenerationId === null) {
            return;
        }

        // P2.A: hard delete dos não-usados; usados preservados com sua invalidation_reason
        RecoveryCode::query()
            ->where('user_id', $userId)
            ->where('generation_id', $latestGenerationId)
            ->whereNull('used_at')
            ->delete();
    }

    private function generatePlainCode(): string
    {
        $charsetLength = strlen(self::CODE_CHARSET);
        $groups = [];

        for ($groupIndex = 0; $groupIndex < self::CODE_GROUP_COUNT; $groupIndex++) {
            $group = '';

            for ($charIndex = 0; $charIndex < self::CODE_GROUP_LENGTH; $charIndex++) {
                $group .= self::CODE_CHARSET[random_int(0, $charsetLength - 1)];
            }

            $groups[] = $group;
        }

        return implode('-', $groups);
    }
}
