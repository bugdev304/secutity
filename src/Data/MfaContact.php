<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Data;

use Ae3\AuthSecurity\Enums\MfaChannel;

readonly class MfaContact
{
    public function __construct(
        public MfaChannel $channel,
        public string $label,       // rótulo legível para exibição no frontend
        public ?string $identifier = null, // valor real (e-mail ou telefone) — null para authenticator_app, que não envia OTP
    ) {}
}
