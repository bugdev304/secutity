<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Defaults;

use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Illuminate\Support\Facades\Log;

/**
 * Implementação padrão do MfaMessageSender: registra um aviso no log e descarta o OTP.
 * Adequado apenas para desenvolvimento/sandbox sem SMTP/SMS configurado.
 * Em produção, configure message_sender em config/auth-security.php.
 */
class NullMfaMessageSender implements MfaMessageSender
{
    public function sendOtp(string $channel, string $identifier, string $code): void
    {
        Log::warning('auth-security: OTP não enviado — MfaMessageSender não configurado.', [
            'channel' => $channel,
            'identifier' => $identifier,
            // $code nunca é logado intencionalmente
        ]);
    }
}
