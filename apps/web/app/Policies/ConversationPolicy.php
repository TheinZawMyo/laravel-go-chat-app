<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function view(User $actor, Conversation $conversation): bool
    {
        return $conversation->hasActiveMember($actor);
    }

    public function send(User $actor, Conversation $conversation): bool
    {
        return $conversation->hasActiveMember($actor);
    }

    public function read(User $actor, Conversation $conversation): bool
    {
        return $conversation->hasActiveMember($actor);
    }
}
