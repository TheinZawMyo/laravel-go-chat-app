<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_send_messages_with_transactional_sequences(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();

        $first = $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => 'Hello there',
            ]);

        $first
            ->assertCreated()
            ->assertJsonPath('data.seq', 1)
            ->assertJsonPath('data.body', 'Hello there')
            ->assertJsonPath('data.sender.id', $actor->id);

        $second = $this
            ->actingAs($other)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => 'Hi!',
            ]);

        $second
            ->assertCreated()
            ->assertJsonPath('data.seq', 2);

        $this->assertSame(2, $conversation->fresh()->next_message_seq);
        $this->assertSame($second->json('data.id'), $conversation->fresh()->last_message_id);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_sending_the_same_client_message_id_twice_is_idempotent(): void
    {
        [$actor, , $conversation] = $this->directConversation();

        $clientMessageId = (string) Str::uuid();

        $first = $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => $clientMessageId,
                'body' => 'Only once',
            ])
            ->assertCreated();

        $second = $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => $clientMessageId,
                'body' => 'Only once',
            ])
            ->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame(1, $conversation->fresh()->next_message_seq);
    }

    public function test_a_client_message_id_cannot_be_reused_in_another_conversation(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();
        $third = User::factory()->create();
        $otherConversation = app(ConversationService::class)->openDirect($actor, $third);

        $clientMessageId = (string) Str::uuid();

        $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => $clientMessageId,
                'body' => 'First conversation',
            ])
            ->assertCreated();

        $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$otherConversation->id}/messages", [
                'client_message_id' => $clientMessageId,
                'body' => 'Second conversation',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_message_id');

        $this->assertSame(1, Message::query()->count());
    }

    public function test_non_members_cannot_read_or_write_messages(): void
    {
        [, , $conversation] = $this->directConversation();
        $outsider = User::factory()->create();

        $this
            ->actingAs($outsider)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertForbidden();

        $this
            ->actingAs($outsider)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => 'Nope',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_messages_can_reply_to_other_messages_in_the_same_conversation(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();

        $original = $this->sendMessage($actor, $conversation, 'Original');

        $reply = $this
            ->actingAs($other)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => 'Reply',
                'reply_to_message_id' => $original->id,
            ])
            ->assertCreated();

        $reply->assertJsonPath('data.reply_to.id', $original->id);
        $reply->assertJsonPath('data.reply_to.body', 'Original');
    }

    public function test_cannot_reply_to_a_message_in_another_conversation(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();
        $third = User::factory()->create();
        $otherConversation = app(ConversationService::class)->openDirect($actor, $third);

        $foreign = $this->sendMessage($actor, $otherConversation, 'Elsewhere');

        $this
            ->actingAs($other)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => 'Reply',
                'reply_to_message_id' => $foreign->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reply_to_message_id');
    }

    public function test_unread_counts_track_the_read_cursor_monotonically(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();

        $this->sendMessage($actor, $conversation, 'One');
        $this->sendMessage($actor, $conversation, 'Two');

        $this
            ->actingAs($other)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 2);

        $this
            ->actingAs($other)
            ->postJson("/api/v1/conversations/{$conversation->id}/read", ['seq' => 2])
            ->assertOk()
            ->assertJsonPath('data.last_read_seq', 2);

        $this
            ->actingAs($other)
            ->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.unread_count', 0);

        $this
            ->actingAs($other)
            ->postJson("/api/v1/conversations/{$conversation->id}/read", ['seq' => 1])
            ->assertOk()
            ->assertJsonPath('data.last_read_seq', 2);
    }

    public function test_only_the_sender_can_edit_a_message(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();
        $message = $this->sendMessage($actor, $conversation, 'Before');

        $this
            ->actingAs($other)
            ->patchJson("/api/v1/messages/{$message->id}", ['body' => 'Hijacked'])
            ->assertForbidden();

        $this
            ->actingAs($actor)
            ->patchJson("/api/v1/messages/{$message->id}", ['body' => 'After'])
            ->assertOk()
            ->assertJsonPath('data.body', 'After');

        $this->assertNotNull($message->fresh()->edited_at);
        $this->assertDatabaseHas('message_edits', [
            'message_id' => $message->id,
            'editor_id' => $actor->id,
            'previous_body' => 'Before',
            'current_body' => 'After',
        ]);
    }

    public function test_only_the_sender_can_delete_a_message(): void
    {
        [$actor, $other, $conversation] = $this->directConversation();
        $first = $this->sendMessage($actor, $conversation, 'First');
        $second = $this->sendMessage($actor, $conversation, 'Second');

        $this
            ->actingAs($other)
            ->deleteJson("/api/v1/messages/{$second->id}")
            ->assertForbidden();

        $this
            ->actingAs($actor)
            ->deleteJson("/api/v1/messages/{$second->id}")
            ->assertOk()
            ->assertJsonPath('data.body', null);

        $deleted = $second->fresh();

        $this->assertNotNull($deleted->deleted_at);
        $this->assertNull($deleted->body);
        $this->assertSame($first->id, $conversation->fresh()->last_message_id);

        $this
            ->actingAs($other)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.1.deleted_at', $deleted->deleted_at?->toISOString());
    }

    public function test_message_body_is_required_and_bounded(): void
    {
        [$actor, , $conversation] = $this->directConversation();

        $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this
            ->actingAs($actor)
            ->postJson("/api/v1/conversations/{$conversation->id}/messages", [
                'client_message_id' => (string) Str::uuid(),
                'body' => str_repeat('a', MessageService::MAX_BODY_LENGTH + 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    /**
     * @return array{0: User, 1: User, 2: Conversation}
     */
    private function directConversation(): array
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();

        return [
            $actor,
            $other,
            app(ConversationService::class)->openDirect($actor, $other),
        ];
    }

    private function sendMessage(User $sender, Conversation $conversation, string $body): Message
    {
        [$message] = app(MessageService::class)->send($sender, $conversation, [
            'client_message_id' => (string) Str::uuid(),
            'body' => $body,
        ]);

        return $message;
    }
}
