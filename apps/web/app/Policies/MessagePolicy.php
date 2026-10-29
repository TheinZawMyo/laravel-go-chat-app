<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $actor, Message $message): bool
    {
        return $message->conversation->hasActiveMember($actor);
    }

    public function update(User $actor, Message $message): bool
    {
        return $message->deleted_at === null
            && $message->sender_id === $actor->id
            && $this->view($actor, $message);
    }

    public function delete(User $actor, Message $message): bool
    {
        return $message->deleted_at === null
            && $message->sender_id === $actor->id
            && $this->view($actor, $message);
    }
}
