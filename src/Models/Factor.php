<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Ae3\AuthSecurity\Enums\FactorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factor extends AuthSecurityModel
{
    protected $table = 'factors';

    protected $fillable = [
        'user_id',
        'type',
        'identifier',
        'secret_encrypted',
        'name',
        'confirmed_at',
        'last_used_at',
    ];

    protected $hidden = ['secret_encrypted'];

    protected function casts(): array
    {
        return [
            'type' => FactorType::class,
            'secret_encrypted' => 'encrypted',
            'confirmed_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'));
    }
}
