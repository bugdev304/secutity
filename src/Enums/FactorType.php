<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum FactorType: string
{
    case EMAIL = 'email';
    case SMS = 'sms';
    case AUTHENTICATOR_APP = 'authenticator_app';

    public function label(): string
    {
        return match ($this) {
            FactorType::EMAIL => 'E-mail',
            FactorType::SMS => 'SMS',
            FactorType::AUTHENTICATOR_APP => 'Aplicativo autenticador',
        };
    }

    public function isOtp(): bool
    {
        return match ($this) {
            FactorType::EMAIL, FactorType::SMS => true,
            FactorType::AUTHENTICATOR_APP => false,
        };
    }

    public function isTotp(): bool
    {
        return match ($this) {
            FactorType::AUTHENTICATOR_APP => true,
            FactorType::EMAIL, FactorType::SMS => false,
        };
    }
}
