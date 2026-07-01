<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Testing\Fixtures;

use Ae3\AuthSecurity\Contracts\MfaMessageSender;
use Ae3\AuthSecurity\Enums\MfaChannel;
use Illuminate\Support\Facades\Log;

/**
 * Fixture de teste: loga o OTP no log do Laravel em vez de enviar SMS/e-mail real.
 * Permite testar o fluxo MFA via Postman sem configurar SMTP ou Twilio.
 *
 * O código é gravado em storage/logs/laravel.log no formato:
 *
 *   [OTP sandbox] canal=email para=us***@***.com código=123456
 *
 * ATENÇÃO: use exclusivamente em ambientes de desenvolvimento/teste.
 * Nunca configure em produção — loga o código OTP em texto plano.
 */
class LoggingMessageSender implements MfaMessageSender
{
    public function sendOtp(MfaChannel $channel, string $identifier, string $code): void
    {
        Log::info('[OTP sandbox]', [
            'channel' => $channel->value,
            'identifier' => $identifier,
            'code' => $code,
        ]);
    }
}
