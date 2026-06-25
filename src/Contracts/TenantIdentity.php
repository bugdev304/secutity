<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Contracts;

/**
 * Marca um objeto como identidade de tenant reconhecida pelo pacote.
 * A app consumidora implementa esta interface em seu model de organização/empresa.
 * Exemplo: class Company extends Model implements TenantIdentity { ... }
 */
interface TenantIdentity
{
    public function getTenantKey(): int|string;

    public function getTenantType(): string;
}
