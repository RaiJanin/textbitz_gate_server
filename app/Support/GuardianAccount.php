<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Keeps a client-app account (App\Models\User) and its Guardian profile paired.
 * `guardians.user_id` is NOT NULL, so every guardian points at a user:
 *
 *  - App sign-up / any new User        → ensureGuardianFor()  makes the Guardian.
 *  - Admin-created (or programmatic)    → linkUser() runs on `creating` and sets
 *    Guardian                            `user_id` before the row is inserted.
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

        self::ensurePreferences($user);

        return $guardian;
    }

    /**
     * Resolve the client-app account for a guardian that is being created and set
     * its `user_id` in memory (so the pending INSERT satisfies the NOT NULL
     * column). Creates the login when the mobile number is new; links to it when
     * the number already exists but has no guardian yet.
     *
     * @throws RuntimeException when the guardian can't be paired with a user.
     */
    public static function linkUser(Guardian $guardian): void
    {
        if ($guardian->user_id) {
            return;
        }

        $phone = trim((string) $guardian->phone);

        if ($phone === '') {
            throw new RuntimeException('A guardian needs a mobile number to be paired with an app account.');
        }

        self::$lastGeneratedPassword = null;

        $existing = User::where('phone_number', $phone)->first();

        if ($existing) {
            if ($existing->guardian()->exists()) {
                throw new RuntimeException('That mobile number already has a guardian account.');
            }

            $guardian->user_id = $existing->id;
            self::ensurePreferences($existing);

            return;
        }

        $provided = $guardian->pullPlainPassword();
        $plain = $provided ?? Str::password(12);
        self::$lastGeneratedPassword = $provided === null ? $plain : null;

        $user = self::withoutSync(fn () => User::create([
            'name' => $guardian->name,
            'email' => $guardian->email,
            'phone_number' => $phone,
            'password' => Hash::make($plain),
        ]));

        $guardian->user_id = $user->id;
        self::ensurePreferences($user);
    }

    protected static function ensurePreferences(User $user): void
    {
        $user->notificationPreferences()->firstOrCreate(
            ['role' => NotificationPreference::ROLE_GUARDIAN],
            ['arrival' => true, 'departure' => true, 'late_alert' => true, 'weekly_summary' => true],
        );
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
