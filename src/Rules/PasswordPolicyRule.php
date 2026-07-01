<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Rules;

use Ae3\AuthSecurity\Models\PasswordHistory;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class PasswordPolicyRule implements ValidationRule
{
    public function __construct(
        private readonly ?Authenticatable $user = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $minLength = (int) config('auth-security.password.min_length', 8);
        if (mb_strlen($password) < $minLength) {
            $fail(__('auth-security.password_violation_min_length', ['min' => $minLength]));
        }

        $classesRequired = (int) config('auth-security.password.classes_required', 3);
        $classCount = 0;
        if (preg_match('/[A-Z]/', $password)) {
            $classCount++;
        }
        if (preg_match('/[a-z]/', $password)) {
            $classCount++;
        }
        if (preg_match('/[0-9]/', $password)) {
            $classCount++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $classCount++;
        }
        if ($classCount < $classesRequired) {
            $fail(__('auth-security.password_violation_classes_required', ['required' => $classesRequired]));
        }

        if ($this->user === null) {
            return;
        }

        $historySize = (int) config('auth-security.password.history_size', 0);
        if ($historySize <= 0) {
            return;
        }

        $recentHashes = PasswordHistory::where('user_id', $this->user->getAuthIdentifier())
            ->orderByDesc('created_at')
            ->limit($historySize)
            ->pluck('password_hash');

        foreach ($recentHashes as $storedHash) {
            if (Hash::check($password, $storedHash)) {
                $fail(__('auth-security.password_violation_history'));

                return;
            }
        }
    }
}
