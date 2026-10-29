<?php

namespace Tests\Feature\Policies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_update_and_delete_themselves()
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('update', $user));
        $this->assertTrue($user->can('delete', $user));
    }

    public function test_users_cannot_update_or_delete_other_users()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('update', $other));
        $this->assertFalse($user->can('delete', $other));
    }

    public function test_authenticated_users_can_view_profiles()
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($user->can('view', $other));
        $this->assertTrue($user->can('viewAny', User::class));
    }
}
