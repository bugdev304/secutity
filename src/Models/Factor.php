<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Ae3\AuthSecurity\Enums\FactorType;
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
        'last_used_at',
    ];

    protected $hidden = ['secret_encrypted'];

    protected function casts(): array
    {
        return [
            'type' => FactorType::class,
            'secret_encrypted' => 'encrypted',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'));
    }
}
