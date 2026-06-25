<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends AuthSecurityModel
{
    protected $table = 'password_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'password_hash',
    ];

    protected $hidden = ['password_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth-security.user_model'));
    }
}
