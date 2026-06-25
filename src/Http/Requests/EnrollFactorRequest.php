<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Ae3\AuthSecurity\Enums\FactorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollFactorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(FactorType::class)],
            'identifier' => ['required_unless:type,authenticator_app', 'nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:100'],
            'holder_name' => ['required_if:type,authenticator_app', 'nullable', 'string', 'max:255'],
        ];
    }
}
