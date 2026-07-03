<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * A força da nova senha (tamanho, classes, histórico) é validada por
     * PasswordPolicyService dentro de ChangePasswordAction — não aqui. Isso mantém
     * um único caminho de erro (PasswordPolicyException → WEAK_PASSWORD com
     * violations[]) em vez de duas formas de rejeitar a mesma senha fraca.
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:sanctum'],
            'new_password' => ['required', 'string', 'confirmed'],
        ];
    }
}
