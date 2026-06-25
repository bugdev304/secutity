<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmFactorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
