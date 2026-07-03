<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Enums\AssistedRecoveryReason;
use Ae3\AuthSecurity\Enums\AssistedRecoveryStatus;
use Ae3\AuthSecurity\Models\AssistedRecovery;
use Ae3\AuthSecurity\Models\UserState;
use Ae3\AuthSecurity\Tests\Support\TestUser;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AssistedRecoveryControllerTest extends FeatureTestCase
{
    private TestUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = TestUser::create([
            'email' => 'admin@example.com',
            'password' => bcrypt('AdminPass1!'),
        ]);

        $this->app['config']->set('auth-security.user_model', TestUser::class);
    }

    // ── POST /test-api/mfa/assisted-recoveries ───────────────────────────────

    public function test_store_creates_recovery_for_self(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/test-api/mfa/assisted-recoveries', [
                'target_user_id' => $this->user->id,
                'reason_category' => AssistedRecoveryReason::DEVICE_LOST->value, // 'device_lost'
                'reason_text' => 'Lost my phone',
            ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.status', AssistedRecoveryStatus::REQUESTED->value);

        $this->assertDatabaseHas('assisted_recoveries', [
            'target_user_id' => $this->user->id,
            'status' => AssistedRecoveryStatus::REQUESTED->value,
        ]);
    }

    public function test_store_requires_reason_category(): void
    {
        $response = $this->postJson('/test-api/mfa/assisted-recoveries', []);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── POST /test-api/mfa/assisted-recoveries/{recovery}/release ───────────

    public function test_release_returns_token_and_updates_status(): void
    {
        $recovery = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/test-api/mfa/assisted-recoveries/{$recovery->id}/release");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.status', AssistedRecoveryStatus::RELEASED->value)
            ->assertJsonStructure(['data' => ['recovery_token']]);

        $this->assertDatabaseHas('assisted_recoveries', [
            'id' => $recovery->id,
            'status' => AssistedRecoveryStatus::RELEASED->value,
        ]);
    }

    public function test_release_fails_if_already_terminal(): void
    {
        $recovery = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::COMPLETED,
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/test-api/mfa/assisted-recoveries/{$recovery->id}/release");

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('code', 'INVALID_STATUS');
    }

    // ── POST /test-api/mfa/assisted-recoveries/complete (TEC-11) ─────────────

    public function test_complete_updates_status_and_sets_must_register_factor(): void
    {
        $plainToken = 'secret-token-abcdefghij1234567890abcdefghij1234567890abcdefghij1234567890';
        $recovery = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'executed_by_user_id' => $this->admin->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::RELEASED,
            'recovery_token_hash' => Hash::make($plainToken),
            'token_expires_at' => now()->addHour(),
            'requested_at' => now(),
            'released_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/test-api/mfa/assisted-recoveries/complete', [
                'token' => $plainToken,
            ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.status', AssistedRecoveryStatus::COMPLETED->value)
            ->assertJsonPath('meta.must_register_factor', true);

        $userState = UserState::where('user_id', $this->user->id)->first();
        $this->assertNotNull($userState);
        $this->assertTrue((bool) $userState->must_register_factor);
    }

    public function test_complete_fails_with_invalid_token(): void
    {
        AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'executed_by_user_id' => $this->admin->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::RELEASED,
            'recovery_token_hash' => Hash::make('correct-token'),
            'token_expires_at' => now()->addHour(),
            'requested_at' => now(),
            'released_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/test-api/mfa/assisted-recoveries/complete', [
                'token' => 'wrong-token',
            ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('code', 'INVALID_TOKEN');
    }

    // ── POST /test-api/mfa/assisted-recoveries/{recovery}/refuse ───────────

    public function test_refuse_updates_status_to_refused(): void
    {
        $recovery = AssistedRecovery::create([
            'target_user_id' => $this->user->id,
            'reason_category' => AssistedRecoveryReason::DEVICE_LOST,
            'status' => AssistedRecoveryStatus::REQUESTED,
            'requested_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/test-api/mfa/assisted-recoveries/{$recovery->id}/refuse");

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.status', AssistedRecoveryStatus::REFUSED->value);
    }
}
