<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_fetch_the_current_user()
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_users_can_fetch_their_profile()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.username', $user->username)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.avatar_url', null)
            ->assertJsonMissingPath('data.password');
    }
}
