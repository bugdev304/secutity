<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Models\UserState;
use Symfony\Component\HttpFoundation\Response;

class PasswordControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('auth-security.password.min_length', 8);
        $this->app['config']->set('auth-security.password.classes_required', 0);
        $this->app['config']->set('auth-security.password.history_size', 0);
        $this->app['config']->set('auth-security.password.expiration_days', 0);
    }

    // ── POST /test-api/password ──────────────────────────────────────────────

    public function test_change_password_succeeds(): void
    {
        $response = $this->postJson('/test-api/password', [
            'password' => 'Password1!Abc',
            'new_password' => 'NewPassword9!',
            'new_password_confirmation' => 'NewPassword9!',
        ]);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.changed', true);

        $userState = UserState::where('user_id', $this->user->id)->first();
        $this->assertNotNull($userState?->password_changed_at);
    }

    public function test_change_password_rejects_short_password(): void
    {
        $this->app['config']->set('auth-security.password.min_length', 12);

        $response = $this->postJson('/test-api/password', [
            'password' => 'Password1!Abc',
            'new_password' => 'Short1!',
            'new_password_confirmation' => 'Short1!',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('code', 'WEAK_PASSWORD')
            ->assertJsonStructure(['violations']);
    }

    public function test_change_password_requires_confirmation(): void
    {
        $response = $this->postJson('/test-api/password', [
            'password' => 'Password1!Abc',
            'new_password' => 'NewPassword9!',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_audit_log_is_recorded_on_change(): void
    {
        $this->postJson('/test-api/password', [
            'password' => 'Password1!Abc',
            'new_password' => 'NewPassword9!',
            'new_password_confirmation' => 'NewPassword9!',
        ]);

        $this->assertNotEmpty($this->auditLogger->logged);
        $this->assertSame('password.changed', $this->auditLogger->logged[0]['event']);
    }

    public function test_change_password_revokes_existing_tokens(): void
    {
        $this->user->createToken('session-outros-dispositivos');

        $this->assertCount(1, $this->user->fresh()->tokens);

        $this->postJson('/test-api/password', [
            'password' => 'Password1!Abc',
            'new_password' => 'NewPassword9!',
            'new_password_confirmation' => 'NewPassword9!',
        ]);

        $this->assertCount(0, $this->user->fresh()->tokens);
    }
}
