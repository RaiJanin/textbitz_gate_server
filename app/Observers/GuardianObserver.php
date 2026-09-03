<?php

namespace App\Observers;

use App\Models\Guardian;
use App\Support\GuardianAccount;

class GuardianObserver
{
    /**
     * `guardians.user_id` is NOT NULL, so a guardian created without one (admin
     * panel, tinker, …) is paired with a client-app account *before* it's saved:
     * a new login is minted for a new mobile number, or an existing account is
     * linked. Throws if it can't be paired.
     */
    public function creating(Guardian $guardian): void
    {
        if (GuardianAccount::isSyncing() || $guardian->user_id) {
            return;
        }

        GuardianAccount::linkUser($guardian);
    }
}
