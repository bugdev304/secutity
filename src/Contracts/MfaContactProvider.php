<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

use Ae3\AuthSecurity\Data\MfaContact;

interface MfaContactProvider
{
    /**
     * Retorna os contatos disponíveis do usuário para cadastro de fator MFA.
     *
     * @return MfaContact[]
     */
    public function mfaContacts(): array;
}
