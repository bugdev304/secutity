<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertOrganizationPolicyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tenant_type' => ['required', 'string', 'max:255'],
            'tenant_id' => ['required', 'integer'],
            'role_type' => ['required', 'string', 'max:255'],
            'role_id' => ['required', 'integer'],
            'requires_mfa' => ['required', 'boolean'],
            'context' => ['nullable', 'string', 'max:100'],
        ];
    }
}
