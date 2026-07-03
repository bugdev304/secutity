<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Services;

use Ae3\AuthSecurity\Exceptions\PasswordPolicyException;
use Ae3\AuthSecurity\Models\PasswordHistory;
use Ae3\AuthSecurity\Models\UserState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyService
{
    public function validate(Authenticatable $user, string $newPassword): void
    {
        $violations = [];

        $minLength = config('auth-security.password.min_length');
        if (mb_strlen($newPassword) < $minLength) {
            $violations[] = "min_length:{$minLength}";
        }

        $classesRequired = config('auth-security.password.classes_required');
        $classCount = 0;
        if (preg_match('/[A-Z]/', $newPassword)) {
            $classCount++;
        }
        if (preg_match('/[a-z]/', $newPassword)) {
            $classCount++;
        }
        if (preg_match('/[0-9]/', $newPassword)) {
            $classCount++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $classCount++;
        }
        if ($classCount < $classesRequired) {
            $violations[] = "classes_required:{$classesRequired}";
        }

        $historySize = config('auth-security.password.history_size');
        if ($historySize > 0) {
            $recentHashes = PasswordHistory::where('user_id', $user->getAuthIdentifier())
                ->orderByDesc('created_at')
                ->limit($historySize)
                ->pluck('password_hash');

            foreach ($recentHashes as $storedHash) {
                if (Hash::check($newPassword, $storedHash)) {
                    $violations[] = 'password_in_history';
                    break;
                }
            }
        }

        if ($violations !== []) {
            throw new PasswordPolicyException($violations);
        }
    }

    public function record(Authenticatable $user, string $hashedPassword): void
    {
        DB::transaction(function () use ($user, $hashedPassword) {
            PasswordHistory::create([
                'user_id' => $user->getAuthIdentifier(),
                'password_hash' => $hashedPassword,
            ]);

            $historySize = config('auth-security.password.history_size');
            if ($historySize > 0) {
                $identifiersToKeep = PasswordHistory::where('user_id', $user->getAuthIdentifier())
                    ->orderByDesc('created_at')
                    ->limit($historySize)
                    ->pluck('id');

                PasswordHistory::where('user_id', $user->getAuthIdentifier())
                    ->whereNotIn('id', $identifiersToKeep)
                    ->delete();
            }
        });
    }

    public function isExpired(Authenticatable $user): bool
    {
        $expirationDays = config('auth-security.password.expiration_days');
        if (! $expirationDays) {
            return false;
        }

        $state = UserState::where('user_id', $user->getAuthIdentifier())->first();
        if ($state === null || $state->password_changed_at === null) {
            return false;
        }

        return $state->password_changed_at->addDays($expirationDays)->isPast();
    }
}
