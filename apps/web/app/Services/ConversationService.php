<?php

namespace App\Services;

use App\Enums\ConversationRole;
use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConversationService
{
    /**
     * Open the direct conversation between two users, creating it once.
     */
    public function openDirect(User $actor, User $other): Conversation
    {
        if ($actor->is($other)) {
            throw ValidationException::withMessages([
                'username' => __('You cannot start a conversation with yourself.'),
            ]);
        }

        if (! $other->isActive()) {
            throw ValidationException::withMessages([
                'username' => __('This account is not available.'),
            ]);
        }

        $pairKey = Conversation::directPairKey($actor->id, $other->id);

        try {
            return DB::transaction(function () use ($actor, $other, $pairKey): Conversation {
                $conversation = Conversation::query()
                    ->where('direct_pair_key', $pairKey)
                    ->lockForUpdate()
                    ->first();

                if ($conversation !== null) {
                    return $conversation;
                }

                $conversation = Conversation::query()->create([
                    'type' => ConversationType::Direct,
                    'created_by' => $actor->id,
                    'direct_pair_key' => $pairKey,
                ]);

                $joinedAt = Carbon::now();

                $conversation->members()->attach($actor->id, [
                    'role' => ConversationRole::Member->value,
                    'joined_at' => $joinedAt,
                ]);

                $conversation->members()->attach($other->id, [
                    'role' => ConversationRole::Member->value,
                    'joined_at' => $joinedAt,
                ]);

                return $conversation;
            });
        } catch (UniqueConstraintViolationException) {
            $existing = Conversation::query()
                ->where('direct_pair_key', $pairKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'username' => __('The conversation could not be created. Please try again.'),
            ]);
        }
    }

    /**
     * Load the data every conversation representation needs for one viewer.
     *
     * @return Collection<int, Conversation>
     */
    public function summariesFor(User $user): Collection
    {
        $conversations = Conversation::query()
            ->whereHas('members', function (Builder $query) use ($user): void {
                $query->whereKey($user->id)
                    ->whereNull('conversation_members.left_at');
            })
            ->with(['lastMessage.sender', 'members'])
            ->orderByDesc('updated_at')
            ->get();

        $unreadCounts = $this->unreadCountsFor($user, $conversations->pluck('id'));

        return $conversations->each(
            fn (Conversation $conversation): Conversation => $this->applyViewerState(
                $conversation,
                $user,
                $unreadCounts[$conversation->id] ?? 0,
            ),
        );
    }

    public function prepareForViewer(User $user, Conversation $conversation): Conversation
    {
        $conversation->loadMissing(['lastMessage.sender', 'members']);

        $unreadCounts = $this->unreadCountsFor($user, collect([$conversation->id]));

        return $this->applyViewerState(
            $conversation,
            $user,
            $unreadCounts[$conversation->id] ?? 0,
        );
    }

    /**
     * Advance a membership's read cursor without allowing it to move backwards.
     */
    public function markRead(User $user, Conversation $conversation, int $seq): int
    {
        $seq = max(0, min($seq, (int) $conversation->next_message_seq));

        DB::table('conversation_members')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('last_read_seq', '<', $seq)
            ->update([
                'last_read_seq' => $seq,
                'updated_at' => Carbon::now(),
            ]);

        $lastReadSeq = DB::table('conversation_members')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->value('last_read_seq');

        return (int) $lastReadSeq;
    }

    /**
     * @param  Collection<int, string>  $conversationIds
     * @return array<string, int>
     */
    private function unreadCountsFor(User $user, Collection $conversationIds): array
    {
        if ($conversationIds->isEmpty()) {
            return [];
        }

        return DB::table('messages')
            ->join('conversation_members', function ($join) use ($user): void {
                $join->on('conversation_members.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_members.user_id', '=', $user->id)
                    ->whereNull('conversation_members.left_at');
            })
            ->whereIn('messages.conversation_id', $conversationIds)
            ->whereColumn('messages.seq', '>', 'conversation_members.last_read_seq')
            ->where('messages.sender_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'conversation_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function applyViewerState(Conversation $conversation, User $user, int $unreadCount): Conversation
    {
        $membership = $conversation->members->firstWhere('id', $user->id);

        $conversation->setAttribute('viewer_last_read_seq', (int) ($membership?->pivot->last_read_seq ?? 0));
        $conversation->setAttribute('unread_count', $unreadCount);

        return $conversation;
    }
}
