<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Ae3\AuthSecurity\Enums\RecoveryCodeInvalidationReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryCode extends AuthSecurityModel
{
    protected $table = 'recovery_codes';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'code_hash',
        'generation_id',
        'used_at',
        'invalidation_reason',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'invalidation_reason' => RecoveryCodeInvalidationReason::class,
            'used_at' => 'datetime',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('used_at');
    }

    public function isAvailable(): bool
    {
        return $this->used_at === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'));
    }
}
