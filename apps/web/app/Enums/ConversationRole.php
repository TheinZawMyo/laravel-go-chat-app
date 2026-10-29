<?php

namespace App\Enums;

enum ConversationRole: string
{
    case Member = 'member';
    case Admin = 'admin';
    case Owner = 'owner';
}
