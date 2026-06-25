<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistedRecovery extends AuthSecurityModel
{
    protected $table = 'assisted_recoveries';

    protected $hidden = ['recovery_token_hash'];

    protected $fillable = [
        'target_user_id',
        'executed_by_user_id',
        'reason_category',
        'reason_text',
        'status',
        'recovery_token_hash',
        'token_expires_at',
        'requested_at',
        'released_at',
        'completed_at',
        'refused_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_category' => AssistedRecoveryReason::class,
            'status' => AssistedRecoveryStatus::class,
            'token_expires_at' => 'datetime',
            'requested_at' => 'datetime',
            'released_at' => 'datetime',
            'completed_at' => 'datetime',
            'refused_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AssistedRecoveryStatus::Requested->value,
            AssistedRecoveryStatus::InAnalysis->value,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'), 'target_user_id');
    }

    public function executedByUser(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'), 'executed_by_user_id');
    }
}
