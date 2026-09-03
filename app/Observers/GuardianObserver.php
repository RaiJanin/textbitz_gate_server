<?php

namespace App\Observers;

use App\Models\Guardian;
use App\Support\GuardianAccount;

class GuardianObserver
{
    /**
     * A Guardian created by an admin (no linked user, but with a phone) is given
     * a client-app login so the guardian can sign in on the mobile app.
     */
    public function created(Guardian $guardian): void
    {
        if (GuardianAccount::isSyncing()) {
            return;
        }

        GuardianAccount::ensureUserFor($guardian);
    }
}
