<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A guardian profile — always a 1:1 facet of a client-app account
 * (App\Models\User); `user_id` is NOT NULL. App\Support\GuardianAccount keeps
 * the two paired in both directions: a self-service sign-up creates the
 * Guardian; an admin-created Guardian provisions the User login.
 *
 * There is no factory on purpose — build one via `User::factory()->create()`
 * then `$user->guardian` (the UserObserver creates it).
 *
 * `password` is a write-only virtual attribute: set it on create to choose the
 * login password for the paired User; it is never a column here.
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'role', 'password'])]
class Guardian extends Model
{
    protected $attributes = [
        'role' => \App\Support\Relationship::DEFAULT,
    ];

    /** Plaintext password captured from a form, consumed by GuardianAccount. */
    protected ?string $plainPassword = null;

    public function setPasswordAttribute(?string $value): void
    {
        $this->plainPassword = filled($value) ? $value : null;
    }

    public function pullPlainPassword(): ?string
    {
        $password = $this->plainPassword;
        $this->plainPassword = null;

        return $password;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)
            ->withPivot('relationship')
            ->withTimestamps();
    }
}
