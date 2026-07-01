<?php

declare(strict_types=1);

namespace Ae3\AuthSecurity\Tests\Feature;

use Ae3\AuthSecurity\Enums\MfaChannel;
use Ae3\AuthSecurity\Support\ContactTokenizer;
use Symfony\Component\HttpFoundation\Response;

class MfaContactControllerTest extends FeatureTestCase
{
    // ── GET /test-api/mfa/contacts ───────────────────────────────────────────

    public function test_index_returns_masked_identifier_not_plain(): void
    {
        $response = $this->getJson('/test-api/mfa/contacts');

        $response->assertStatus(Response::HTTP_OK);

        $data = $response->json('data.0');

        $this->assertArrayNotHasKey('identifier', $data);
        $this->assertArrayHasKey('masked_identifier', $data);
        $this->assertStringNotContainsString($this->user->email, $data['masked_identifier']);
    }

    public function test_index_returns_contact_token(): void
    {
        $response = $this->getJson('/test-api/mfa/contacts');

        $response->assertStatus(Response::HTTP_OK);

        $contactToken = $response->json('data.0.contact_token');

        $expectedToken = ContactTokenizer::generate(MfaChannel::EMAIL, $this->user->email);

        $this->assertSame($expectedToken, $contactToken);
    }

    public function test_index_returns_channel_and_label(): void
    {
        $response = $this->getJson('/test-api/mfa/contacts');

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.0.channel', 'email')
            ->assertJsonPath('data.0.label', 'E-mail');
    }

    public function test_contact_token_resolves_back_to_correct_contact(): void
    {
        $response = $this->getJson('/test-api/mfa/contacts');

        $contactToken = $response->json('data.0.contact_token');

        $resolved = ContactTokenizer::resolve($this->user, $contactToken);

        $this->assertNotNull($resolved);
        $this->assertSame($this->user->email, $resolved->identifier);
        $this->assertSame(MfaChannel::EMAIL, $resolved->channel);
    }
}
