<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Enums;

enum FactorType: string
{
    case Email = 'email';
    case Sms = 'sms';
    case AuthenticatorApp = 'authenticator_app';

    public function label(): string
    {
        return match ($this) {
            FactorType::Email => 'E-mail',
            FactorType::Sms => 'SMS',
            FactorType::AuthenticatorApp => 'Aplicativo autenticador',
        };
    }

    public function isOtp(): bool
    {
        return match ($this) {
            FactorType::Email, FactorType::Sms => true,
            FactorType::AuthenticatorApp => false,
        };
    }

    public function isTotp(): bool
    {
        return match ($this) {
            FactorType::AuthenticatorApp => true,
            FactorType::Email, FactorType::Sms => false,
        };
    }
}
