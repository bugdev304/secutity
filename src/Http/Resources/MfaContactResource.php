<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Resources;

use Ae3\AuthSecurity\Data\MfaContact;
use Ae3\AuthSecurity\Support\ContactTokenizer;
use Ae3\AuthSecurity\Support\IdentifierMasker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property MfaContact $resource */
class MfaContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'channel' => $this->resource->channel->value,
            'masked_identifier' => IdentifierMasker::mask($this->resource->identifier),
            'label' => $this->resource->label,
            'contact_token' => ContactTokenizer::generate($this->resource->channel, $this->resource->identifier),
        ];
    }
}
