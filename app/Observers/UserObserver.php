<?php

namespace App\Observers;

use App\Models\User;
use App\Support\GuardianAccount;

class UserObserver
{
    /**
     * Every client-app account gets a matching Guardian profile + default
     * guardian notification preferences.
     */
    public function created(User $user): void
    {
        if (GuardianAccount::isSyncing()) {
            return;
        }

        GuardianAccount::ensureGuardianFor($user);
    }
}
