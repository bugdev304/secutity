<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Services;

use Ae3\AuthSecurity\Enums\RecoveryCodeInvalidationReason;
use Ae3\AuthSecurity\Exceptions\RecoveryCodeInvalidException;
use Ae3\AuthSecurity\Models\RecoveryCode;
use Ae3\AuthSecurity\Services\RecoveryCodeService;
use Ae3\AuthSecurity\Tests\DatabaseTestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;

class RecoveryCodeServiceTest extends DatabaseTestCase
{
    private RecoveryCodeService $service;

    private Authenticatable $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RecoveryCodeService;

        $this->user = Mockery::mock(Authenticatable::class);
        $this->user->shouldReceive('getAuthIdentifier')->andReturn(1);
    }

    public function test_generate_creates_configured_number_of_codes(): void
    {
        $codes = $this->service->generate($this->user);

        $expectedCount = config('auth-security.mfa.recovery_codes_count', 8);
        $this->assertCount($expectedCount, $codes);
        $this->assertEquals($expectedCount, RecoveryCode::where('user_id', 1)->count());
    }

    public function test_generated_codes_match_4_4_4_alpha_format(): void
    {
        $codes = $this->service->generate($this->user);

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression(
                '/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/',
                $code,
                "Código '{$code}' não corresponde ao formato 4-4-4-alpha.",
            );
        }
    }

    public function test_all_codes_in_same_generation_share_generation_id(): void
    {
        $this->service->generate($this->user);

        $generationIds = RecoveryCode::where('user_id', 1)->pluck('generation_id')->unique();
        $this->assertCount(1, $generationIds);
    }

    public function test_regeneration_hard_deletes_unused_codes_from_previous_generation(): void
    {
        $this->service->generate($this->user);
        $expectedCount = config('auth-security.mfa.recovery_codes_count', 8);

        $this->service->generate($this->user);

        // Nova leva substitui a anterior; contagem permanece igual
        $this->assertEquals($expectedCount, RecoveryCode::where('user_id', 1)->count());
    }

    public function test_regeneration_preserves_used_codes_from_previous_generation(): void
    {
        $codes = $this->service->generate($this->user);
        $this->service->verify($this->user, $codes[0]); // usa um código

        $this->service->generate($this->user); // regenera

        $expectedCount = config('auth-security.mfa.recovery_codes_count', 8);
        // Nova leva (8) + 1 código já-usado da leva anterior = 9
        $this->assertEquals($expectedCount + 1, RecoveryCode::where('user_id', 1)->count());
    }

    public function test_verify_returns_recovery_code_on_success(): void
    {
        $codes = $this->service->generate($this->user);

        $recoveryCode = $this->service->verify($this->user, $codes[0]);

        $this->assertInstanceOf(RecoveryCode::class, $recoveryCode);
    }

    public function test_verify_marks_code_as_used_with_correct_reason(): void
    {
        $codes = $this->service->generate($this->user);
        $this->service->verify($this->user, $codes[0]);

        $usedCode = RecoveryCode::where('user_id', 1)->whereNotNull('used_at')->first();
        $this->assertNotNull($usedCode);
        $this->assertEquals(RecoveryCodeInvalidationReason::Used, $usedCode->invalidation_reason);
    }

    public function test_verify_throws_when_code_is_invalid(): void
    {
        $this->service->generate($this->user);

        $this->expectException(RecoveryCodeInvalidException::class);
        $this->service->verify($this->user, 'AAAA-BBBB-CCCC');
    }

    public function test_verify_throws_when_code_already_used(): void
    {
        $codes = $this->service->generate($this->user);
        $this->service->verify($this->user, $codes[0]);

        $this->expectException(RecoveryCodeInvalidException::class);
        $this->service->verify($this->user, $codes[0]);
    }

    public function test_verify_throws_when_no_codes_exist(): void
    {
        $this->expectException(RecoveryCodeInvalidException::class);
        $this->service->verify($this->user, 'AAAA-BBBB-CCCC');
    }

    public function test_codes_from_different_users_are_isolated(): void
    {
        $otherUser = Mockery::mock(Authenticatable::class);
        $otherUser->shouldReceive('getAuthIdentifier')->andReturn(99);

        $codesUser1 = $this->service->generate($this->user);
        $this->service->generate($otherUser);

        // Código do usuário 1 não deve funcionar para o usuário 99
        $this->expectException(RecoveryCodeInvalidException::class);
        $this->service->verify($otherUser, $codesUser1[0]);
    }
}
