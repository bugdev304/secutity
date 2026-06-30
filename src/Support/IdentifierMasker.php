<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Support;

class IdentifierMasker
{
    public static function mask(?string $identifier): ?string
    {
        if ($identifier === null) {
            return null;
        }

        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', $identifier, 2);

            $masked = substr($local, 0, 2).str_repeat('*', max(0, strlen($local) - 2));

            return "{$masked}@{$domain}";
        }

        // Telefone: mostra os 4 últimos dígitos
        return str_repeat('*', max(0, strlen($identifier) - 4)).substr($identifier, -4);
    }
}
