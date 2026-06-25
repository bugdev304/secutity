<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Data;

/** Resultado de RegisterTotpAction — exibir ao usuário e nunca persistir. */
readonly class TotpRegistrationData
{
    public function __construct(
        public int $factorId,
        public string $plainSecret,
        public string $qrCodeUri,
        public string $qrCodeInline,
    ) {}
}
