<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Models\User;
use App\Services\GatewayToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GatewayTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_request_a_gateway_token(): void
    {
        $this->getJson('/api/v1/gateway/token')->assertUnauthorized();
    }

    public function test_authenticated_users_receive_a_scoped_short_lived_token(): void
    {
        config([
            'services.gateway.token_secret' => 'testing-gateway-secret',
            'services.gateway.token_ttl' => 120,
            'services.gateway.url' => 'ws://gateway.test:8081',
        ]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/gateway/token')
            ->assertOk()
            ->assertJsonPath('data.url', 'ws://gateway.test:8081');

        $token = (string) $response->json('data.token');

        $this->assertNotSame('', $token);

        $claims = (new GatewayToken('testing-gateway-secret'))->verify($token);

        $this->assertNotNull($claims);
        $this->assertSame($user->id, $claims['sub']);
        $this->assertSame('gateway', $claims['aud']);
        $this->assertSame(120, $claims['exp'] - $claims['iat']);
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_inactive_users_cannot_request_a_gateway_token(): void
    {
        $user = User::factory()->create(['status' => AccountStatus::Suspended]);

        $this
            ->actingAs($user)
            ->getJson('/api/v1/gateway/token')
            ->assertForbidden();
    }

    public function test_expired_and_tampered_tokens_are_rejected(): void
    {
        $service = new GatewayToken('testing-gateway-secret', 60);

        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-29 00:00:00');
        $issued = $service->issue($user);

        $this->assertNotNull($service->verify($issued['token']));

        Carbon::setTestNow('2026-10-29 00:02:00');
        $this->assertNull($service->verify($issued['token']));

        $tampered = substr($issued['token'], 0, -1).'x';
        $this->assertNull($service->verify($tampered));

        Carbon::setTestNow();
    }
}
