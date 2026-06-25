<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

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
            'masked_identifier' => $this->maskIdentifier($this->identifier),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function maskIdentifier(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', $identifier, 2);
            $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2));

            return "{$masked}@{$domain}";
        }

        // Telefone: mostra os 4 últimos dígitos
        return str_repeat('*', max(0, strlen($identifier) - 4)).substr($identifier, -4);
    }
}
