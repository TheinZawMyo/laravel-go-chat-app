<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/chats')->assertRedirect('/login');
    }

    public function test_authenticated_users_see_their_conversation_list(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create(['name' => 'Alice']);
        app(ConversationService::class)->openDirect($actor, $other);

        $this
            ->actingAs($actor)
            ->get('/chats')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Chat')
                ->has('conversations', 1)
                ->where('conversations.0.participant.name', 'Alice')
                ->where('activeConversation', null)
                ->has('activeMessages', 0)
                ->where('currentUserId', $actor->id));
    }

    public function test_opening_a_conversation_loads_its_messages(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $conversation = app(ConversationService::class)->openDirect($actor, $other);

        app(MessageService::class)->send($actor, $conversation, [
            'client_message_id' => (string) Str::uuid(),
            'body' => 'Hello',
        ]);

        $this
            ->actingAs($actor)
            ->get("/chats/{$conversation->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Chat')
                ->where('activeConversation.id', $conversation->id)
                ->has('activeMessages', 1)
                ->where('activeMessages.0.body', 'Hello')
                ->where('activeHasMore', false)
                ->where('activeNextCursor', 1));
    }

    public function test_users_cannot_open_conversations_they_do_not_belong_to(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $stranger = User::factory()->create();
        $conversation = app(ConversationService::class)->openDirect($actor, $other);

        $this
            ->actingAs($stranger)
            ->get("/chats/{$conversation->id}")
            ->assertForbidden();
    }
}
