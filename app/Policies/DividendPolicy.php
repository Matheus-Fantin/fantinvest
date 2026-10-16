<?php

namespace App\Policies;

use App\Models\Dividend;
use App\Models\User;

class DividendPolicy
{
    public function update(User $user, Dividend $dividend): bool
    {
        return $dividend->user_id === $user->id;
    }

    public function delete(User $user, Dividend $dividend): bool
    {
        return $dividend->user_id === $user->id;
    }
}
