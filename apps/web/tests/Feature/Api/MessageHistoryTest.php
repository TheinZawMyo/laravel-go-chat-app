<?php

namespace Tests\Feature\Api;

use App\Models\Conversation;
use App\Models\User;
use App\Services\ConversationService;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_is_cursor_paginated_in_ascending_order(): void
    {
        [$actor, $conversation] = $this->conversationWithMessages(60);

        $firstPage = $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.next_cursor', 31);

        $firstPage->assertJsonPath('data.0.seq', 31);
        $firstPage->assertJsonPath('data.29.seq', 60);

        $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?before_seq=31")
            ->assertOk()
            ->assertJsonCount(30, 'data')
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.seq', 1)
            ->assertJsonPath('data.29.seq', 30);
    }

    public function test_history_pages_stay_stable_when_new_messages_arrive(): void
    {
        [$actor, $conversation] = $this->conversationWithMessages(40);

        $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?limit=10")
            ->assertOk()
            ->assertJsonPath('meta.next_cursor', 31)
            ->assertJsonPath('data.0.seq', 31);

        $this->send($actor, $conversation, 'New message');

        $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?before_seq=31&limit=10")
            ->assertOk()
            ->assertJsonPath('data.0.seq', 21)
            ->assertJsonPath('data.9.seq', 30)
            ->assertJsonPath('meta.has_more', true);
    }

    public function test_history_limit_is_bounded(): void
    {
        [$actor, $conversation] = $this->conversationWithMessages(120);

        $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?limit=100")
            ->assertOk()
            ->assertJsonCount(100, 'data');

        $this
            ->actingAs($actor)
            ->getJson("/api/v1/conversations/{$conversation->id}/messages?limit=101")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    /**
     * @return array{0: User, 1: Conversation}
     */
    private function conversationWithMessages(int $count): array
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $conversation = app(ConversationService::class)->openDirect($actor, $other);

        for ($i = 1; $i <= $count; $i++) {
            $this->send($actor, $conversation, "Message {$i}");
        }

        return [$actor, $conversation];
    }

    private function send(User $sender, Conversation $conversation, string $body): void
    {
        app(MessageService::class)->send($sender, $conversation, [
            'client_message_id' => (string) Str::uuid(),
            'body' => $body,
        ]);
    }
}
