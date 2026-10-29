<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_their_own_devices()
    {
        $user = User::factory()->create();
        $owned = UserDevice::factory()->for($user)->create();
        UserDevice::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/devices');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id);
    }

    public function test_users_can_revoke_their_own_devices()
    {
        $user = User::factory()->create();
        $device = UserDevice::factory()->for($user)->create();

        $this
            ->actingAs($user)
            ->deleteJson("/api/v1/devices/{$device->id}")
            ->assertNoContent();

        $this->assertNotNull($device->refresh()->revoked_at);
    }

    public function test_users_cannot_revoke_other_devices()
    {
        $user = User::factory()->create();
        $device = UserDevice::factory()->create();

        $this
            ->actingAs($user)
            ->deleteJson("/api/v1/devices/{$device->id}")
            ->assertForbidden();

        $this->assertNull($device->refresh()->revoked_at);
    }

    public function test_guests_cannot_access_devices()
    {
        $this->getJson('/api/v1/devices')->assertUnauthorized();
    }
}
