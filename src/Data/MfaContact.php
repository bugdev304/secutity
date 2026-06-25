<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Data;

readonly class MfaContact
{
    public function __construct(
        public string $channel,    // 'email' | 'sms' | outro canal suportado pelo MfaMessageSender da app
        public string $identifier, // valor real (e-mail ou telefone)
        public string $label,      // rótulo legível para exibição no frontend
    ) {}
}
