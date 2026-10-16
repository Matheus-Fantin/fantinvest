<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    public function update(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }
}
