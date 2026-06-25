<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChallengeRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }
}
