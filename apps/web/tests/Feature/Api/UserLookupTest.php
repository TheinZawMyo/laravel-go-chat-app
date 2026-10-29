<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_found_by_username()
    {
        $viewer = User::factory()->create();
        User::factory()->create([
            'name' => 'Alice',
            'username' => 'alice',
            'bio' => 'Hello',
        ]);

        $response = $this
            ->actingAs($viewer)
            ->getJson('/api/v1/users/ALICE');

        $response
            ->assertOk()
            ->assertJsonPath('data.username', 'alice')
            ->assertJsonPath('data.name', 'Alice')
            ->assertJsonPath('data.bio', 'Hello')
            ->assertJsonMissingPath('data.email');
    }

    public function test_unknown_usernames_return_not_found()
    {
        $viewer = User::factory()->create();

        $this
            ->actingAs($viewer)
            ->getJson('/api/v1/users/missing')
            ->assertNotFound();
    }

    public function test_suspended_users_are_not_exposed()
    {
        $viewer = User::factory()->create();
        User::factory()->create([
            'username' => 'suspended',
            'status' => AccountStatus::Suspended,
        ]);

        $this
            ->actingAs($viewer)
            ->getJson('/api/v1/users/suspended')
            ->assertNotFound();
    }

    public function test_guests_cannot_look_up_users()
    {
        User::factory()->create(['username' => 'alice']);

        $this->getJson('/api/v1/users/alice')->assertUnauthorized();
    }
}
