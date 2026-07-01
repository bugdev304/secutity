<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Ae3\AuthSecurity\Rules\PasswordPolicyRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:sanctum'],
            'new_password' => ['required', 'string', 'confirmed', new PasswordPolicyRule($this->user())],
        ];
    }
}
