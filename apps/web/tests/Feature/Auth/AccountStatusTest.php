<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_users_cannot_log_in()
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Suspended,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_existing_sessions_are_invalidated_when_an_account_is_suspended()
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Suspended,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_suspended_users_receive_a_forbidden_api_response()
    {
        $user = User::factory()->create([
            'status' => AccountStatus::Suspended,
        ]);

        $this->actingAs($user)
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }

    public function test_active_users_can_log_in()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }
}
