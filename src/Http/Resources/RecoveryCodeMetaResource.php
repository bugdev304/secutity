<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

use Ae3\AuthSecurity\Models\RecoveryCode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retorna apenas metadados dos códigos de recuperação — nunca os códigos em si.
 *
 * @property Authenticatable $resource
 */
class RecoveryCodeMetaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $this->resource->getAuthIdentifier();

        $latestGeneration = RecoveryCode::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();

        if ($latestGeneration === null) {
            return [
                'generation_id' => null,
                'total' => 0,
                'remaining' => 0,
                'last_generated_at' => null,
            ];
        }

        $total = RecoveryCode::where('user_id', $userId)
            ->where('generation_id', $latestGeneration->generation_id)
            ->count();

        $remaining = RecoveryCode::where('user_id', $userId)
            ->where('generation_id', $latestGeneration->generation_id)
            ->whereNull('used_at')
            ->count();

        return [
            'generation_id' => $latestGeneration->generation_id,
            'total' => $total,
            'remaining' => $remaining,
            'last_generated_at' => $latestGeneration->created_at?->toIso8601String(),
        ];
    }
}
