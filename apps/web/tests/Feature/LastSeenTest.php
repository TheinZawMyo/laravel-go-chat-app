<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastSeenTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_requests_update_last_seen_at()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'last_seen_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertNotNull($user->refresh()->last_seen_at);
    }
}
