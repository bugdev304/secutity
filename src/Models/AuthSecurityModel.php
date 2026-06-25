<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class AuthSecurityModel extends Model
{
    public function getTable(): string
    {
        $schema = config('auth-security.schema', 'auth_security');
        $tableName = isset($this->table)
            ? $this->table
            : Str::snake(Str::pluralStudly(class_basename($this)));

        // Schema vazio desativa o prefixo — usado em testes SQLite
        if ($schema === null || $schema === '') {
            return $tableName;
        }

        return "{$schema}.{$tableName}";
    }
}
