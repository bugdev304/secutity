<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserState extends AuthSecurityModel
{
    protected $table = 'user_state';

    protected $fillable = [
        'user_id',
        'password_changed_at',
        'account_locked_at',
        'account_unlocked_by_user_id',
        'account_unlocked_at',
        'must_register_factor',
        'recovery_refused_at',
    ];

    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
            'account_locked_at' => 'datetime',
            'account_unlocked_at' => 'datetime',
            'must_register_factor' => 'boolean',
            'recovery_refused_at'  => 'datetime',
        ];
    }

    public function isLocked(): bool
    {
        return $this->account_locked_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'));
    }

    public function unlockedByUser(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'), 'account_unlocked_by_user_id');
    }
}
