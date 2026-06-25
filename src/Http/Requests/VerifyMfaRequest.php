<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Ae3\AuthSecurity\Enums\FactorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyMfaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'factor_id' => ['required', 'integer'],
            'factor_type' => ['required', Rule::enum(FactorType::class)],
            'code' => ['required', 'string'],
        ];
    }
}
