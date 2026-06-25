<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestAssistedRecoveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'target_user_id' => ['required', 'integer'],
            'reason_category' => ['required', Rule::enum(AssistedRecoveryReason::class)],
            'reason_text' => ['nullable', 'required_if:reason_category,other', 'string', 'max:1000'],
        ];
    }
}
