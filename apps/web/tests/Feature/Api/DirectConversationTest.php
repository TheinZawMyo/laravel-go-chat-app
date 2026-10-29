<?php

namespace Tests\Feature\Api;

use App\Enums\AccountStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_open_a_direct_conversation(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create(['username' => 'alice', 'name' => 'Alice']);

        $response = $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => 'ALICE']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'direct')
            ->assertJsonPath('data.participant.id', $other->id)
            ->assertJsonPath('data.participant.username', 'alice')
            ->assertJsonPath('data.unread_count', 0)
            ->assertJsonPath('data.last_read_seq', 0);

        $conversation = Conversation::query()->sole();

        $this->assertSame(
            Conversation::directPairKey($actor->id, $other->id),
            $conversation->direct_pair_key,
        );
        $this->assertSame(2, $conversation->members()->count());
    }

    public function test_opening_the_same_direct_conversation_twice_returns_the_existing_conversation(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create(['username' => 'alice']);

        $first = $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => 'alice'])
            ->assertCreated()
            ->json('data.id');

        $second = $this
            ->actingAs($other)
            ->postJson('/api/v1/conversations/direct', ['username' => $actor->username])
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('conversations', 1);
    }

    public function test_users_cannot_start_a_conversation_with_themselves(): void
    {
        $actor = User::factory()->create();

        $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => $actor->username])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_unknown_usernames_are_rejected(): void
    {
        $actor = User::factory()->create();

        $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => 'missing'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('username');
    }

    public function test_inactive_users_cannot_be_contacted(): void
    {
        $actor = User::factory()->create();
        User::factory()->create([
            'username' => 'suspended',
            'status' => AccountStatus::Suspended,
        ]);

        $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => 'suspended'])
            ->assertNotFound();
    }

    public function test_guests_cannot_open_conversations(): void
    {
        User::factory()->create(['username' => 'alice']);

        $this
            ->postJson('/api/v1/conversations/direct', ['username' => 'alice'])
            ->assertUnauthorized();
    }

    public function test_conversation_index_only_lists_conversations_the_user_belongs_to(): void
    {
        $actor = User::factory()->create();
        $friend = User::factory()->create(['username' => 'friend']);

        $this
            ->actingAs($actor)
            ->postJson('/api/v1/conversations/direct', ['username' => 'friend']);

        $stranger = User::factory()->create();

        $this
            ->actingAs($actor)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.participant.id', $friend->id);

        $this
            ->actingAs($stranger)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
