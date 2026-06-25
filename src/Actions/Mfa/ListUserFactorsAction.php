<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Actions\Mfa;

use Ae3\AuthSecurity\Models\Factor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

class ListUserFactorsAction
{
    /** Retorna apenas fatores confirmados — seed e token nunca expostos via $hidden. */
    public function execute(Authenticatable $user, bool $includeUnconfirmed = false): Collection
    {
        $query = Factor::where('user_id', $user->getAuthIdentifier());

        if (! $includeUnconfirmed) {
            $query->confirmed();
        }

        return $query->orderBy('confirmed_at')->get();
    }
}
