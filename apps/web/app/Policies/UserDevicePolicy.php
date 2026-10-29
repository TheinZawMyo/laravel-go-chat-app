<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDevice;

class UserDevicePolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, UserDevice $device): bool
    {
        return $actor->id === $device->user_id;
    }

    public function create(User $actor): bool
    {
        return true;
    }

    public function update(User $actor, UserDevice $device): bool
    {
        return $actor->id === $device->user_id;
    }

    public function delete(User $actor, UserDevice $device): bool
    {
        return $actor->id === $device->user_id;
    }
}
