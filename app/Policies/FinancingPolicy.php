<?php

namespace App\Policies;

use App\Models\Financing;
use App\Models\User;

class FinancingPolicy
{
    public function update(User $user, Financing $financing): bool
    {
        return $financing->user_id === $user->id;
    }

    public function delete(User $user, Financing $financing): bool
    {
        return $financing->user_id === $user->id;
    }
}
