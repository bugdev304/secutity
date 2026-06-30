<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

use Ae3\AuthSecurity\Support\IdentifierMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FactorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'masked_identifier' => IdentifierMasker::mask($this->identifier),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

}
