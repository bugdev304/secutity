<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Models\RecoveryCode;
use Symfony\Component\HttpFoundation\Response;

class RecoveryCodeControllerTest extends FeatureTestCase
{
    // ── GET /test-api/mfa/recovery-codes ────────────────────────────────────

    public function test_show_returns_zero_metadata_when_no_codes(): void
    {
        $response = $this->getJson('/test-api/mfa/recovery-codes');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.generation_id', null);
    }

    public function test_show_returns_correct_metadata_when_codes_exist(): void
    {
        $generationId = 'gen-001';
        RecoveryCode::create([
            'user_id' => $this->user->id,
            'generation_id' => $generationId,
            'code_hash' => bcrypt('CODE001'),
        ]);
        RecoveryCode::create([
            'user_id' => $this->user->id,
            'generation_id' => $generationId,
            'code_hash' => bcrypt('CODE002'),
            'used_at' => now(),
        ]);

        $response = $this->getJson('/test-api/mfa/recovery-codes');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.remaining', 1)
            ->assertJsonPath('data.generation_id', $generationId);
    }

    // ── POST /test-api/mfa/recovery-codes ───────────────────────────────────

    public function test_store_generates_codes_when_none_exist(): void
    {
        $response = $this->postJson('/test-api/mfa/recovery-codes');

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['data' => ['codes']]);

        $codes = $response->json('data.codes');
        $this->assertNotEmpty($codes);
    }

    public function test_store_returns_conflict_when_unused_codes_exist(): void
    {
        RecoveryCode::create([
            'user_id' => $this->user->id,
            'generation_id' => 'gen-001',
            'code_hash' => bcrypt('EXISTING'),
        ]);

        $response = $this->postJson('/test-api/mfa/recovery-codes');

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'INVALIDATION_REQUIRED');
    }

    public function test_store_generates_new_codes_when_confirm_invalidation_is_true(): void
    {
        RecoveryCode::create([
            'user_id' => $this->user->id,
            'generation_id' => 'gen-001',
            'code_hash' => bcrypt('EXISTING'),
        ]);

        $response = $this->postJson('/test-api/mfa/recovery-codes', [
            'confirm_invalidation' => true,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['data' => ['codes']]);
    }
}
