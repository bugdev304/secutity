<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Unit\Enums;

use Ae3\AuthSecurity\Enums\ErrorCode;
use Ae3\AuthSecurity\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ErrorCodeTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function knownCodes(): array
    {
        return [
            'MFA_REQUIRED' => ['MFA_REQUIRED'],
            'MFA_FACTOR_REGISTRATION_REQUIRED' => ['MFA_FACTOR_REGISTRATION_REQUIRED'],
            'ACCOUNT_LOCKED' => ['ACCOUNT_LOCKED'],
            'PASSWORD_EXPIRED' => ['PASSWORD_EXPIRED'],
            'INVALID_CODE' => ['INVALID_CODE'],
            'RESEND_RATE_LIMITED' => ['RESEND_RATE_LIMITED'],
            'RESEND_NOT_ALLOWED' => ['RESEND_NOT_ALLOWED'],
            'WEAK_PASSWORD' => ['WEAK_PASSWORD'],
            'BELOW_FLOOR' => ['BELOW_FLOOR'],
            'INVALID_IDENTIFIER' => ['INVALID_IDENTIFIER'],
            'DUPLICATE_FACTOR' => ['DUPLICATE_FACTOR'],
            'LAST_FACTOR_REQUIRED' => ['LAST_FACTOR_REQUIRED'],
            'INVALID_STATUS' => ['INVALID_STATUS'],
            'INVALID_TOKEN' => ['INVALID_TOKEN'],
            'TOKEN_EXPIRED' => ['TOKEN_EXPIRED'],
            'INVALIDATION_REQUIRED' => ['INVALIDATION_REQUIRED'],
            'AUTH_SECURITY_ERROR' => ['AUTH_SECURITY_ERROR'],
        ];
    }

    #[DataProvider('knownCodes')]
    public function test_error_code_value_matches_the_documented_string(string $expectedValue): void
    {
        $this->assertSame($expectedValue, ErrorCode::from($expectedValue)->value);
    }

    public function test_error_code_has_no_undocumented_cases(): void
    {
        $documentedCases = array_column(self::knownCodes(), 0);

        $this->assertSame($documentedCases, array_map(fn (ErrorCode $case) => $case->value, ErrorCode::cases()));
    }
}
