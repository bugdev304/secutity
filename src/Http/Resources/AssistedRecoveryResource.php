<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistedRecoveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_user_id' => $this->target_user_id,
            'reason_category' => $this->reason_category->value,
            'reason_text' => $this->reason_text,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'token_expires_at' => $this->token_expires_at?->toIso8601String(),
            'requested_at' => $this->requested_at?->toIso8601String(),
            'released_at' => $this->released_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'refused_at' => $this->refused_at?->toIso8601String(),
        ];
    }
}
