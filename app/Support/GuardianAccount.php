<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Keeps a client-app account (App\Models\User) and its Guardian profile in sync,
 * whichever side is created first:
 *
 *  - App sign-up / any new User  → ensureGuardianFor()  makes the Guardian.
 *  - Admin-created Guardian       → ensureUserFor()      makes the User login.
 *
 * A re-entrancy latch stops the two observers from ping-ponging.
 */
class GuardianAccount
{
    protected static bool $syncing = false;

    /** The password generated for the most recent admin-provisioned login, if any. */
    public static ?string $lastGeneratedPassword = null;

    /**
     * Ensure the given user has a Guardian profile + default guardian
     * notification preferences. Idempotent.
     */
    public static function ensureGuardianFor(User $user): Guardian
    {
        $guardian = self::withoutSync(fn () => Guardian::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone_number,
            ],
        ));

        $user->notificationPreferences()->firstOrCreate(
            ['role' => NotificationPreference::ROLE_GUARDIAN],
            ['arrival' => true, 'departure' => true, 'late_alert' => true, 'weekly_summary' => true],
        );

        return $guardian;
    }

    /**
     * Ensure the given guardian has a client-app login. If a user already exists
     * for the guardian's phone it is linked rather than duplicated; otherwise a
     * new one is created (password from the form, or a generated one exposed via
     * self::$lastGeneratedPassword).
     */
    public static function ensureUserFor(Guardian $guardian): ?User
    {
        if ($guardian->user_id) {
            return $guardian->user;
        }

        if (blank($guardian->phone)) {
            return null; // no phone ⇒ can't create a login yet
        }

        self::$lastGeneratedPassword = null;

        $existing = User::where('phone_number', $guardian->phone)->first();

        if ($existing) {
            // That phone already has a client account (and therefore its own
            // guardian profile). This new row is a duplicate — drop it.
            $guardian->deleteQuietly();

            return $existing;
        }

        $provided = $guardian->pullPlainPassword();
        $plain = $provided ?? Str::password(12);
        self::$lastGeneratedPassword = $provided === null ? $plain : null;

        $user = self::withoutSync(fn () => User::create([
            'name' => $guardian->name,
            'email' => $guardian->email,
            'phone_number' => $guardian->phone,
            'password' => Hash::make($plain),
        ]));

        $guardian->forceFill(['user_id' => $user->id])->saveQuietly();

        $user->notificationPreferences()->firstOrCreate(
            ['role' => NotificationPreference::ROLE_GUARDIAN],
            ['arrival' => true, 'departure' => true, 'late_alert' => true, 'weekly_summary' => true],
        );

        return $user;
    }

    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutSync(callable $callback): mixed
    {
        $previous = self::$syncing;
        self::$syncing = true;

        try {
            return $callback();
        } finally {
            self::$syncing = $previous;
        }
    }
}
