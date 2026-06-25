<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

interface MfaMessageSender
{
    /**
     * Envia o código OTP ao usuário pelo canal solicitado.
     *
     * @param  string  $channel  Canal de envio: 'email' | 'sms'
     * @param  string  $identifier  Destino do envio — snapshot do cadastro do fator (RN-SEG15/16),
     *                              nunca sincronizado com o perfil do usuário.
     * @param  string  $code  Código OTP de 6 dígitos em texto plano.
     */
    public function sendOtp(string $channel, string $identifier, string $code): void;
}
