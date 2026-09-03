<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A guardian profile. Always backed by a client-app account (App\Models\User) —
 * App\Support\GuardianAccount keeps the two in sync in both directions:
 * a self-service sign-up creates the Guardian; an admin-created Guardian
 * provisions the User login.
 *
 * `password` is a write-only virtual attribute: set it on create/edit to choose
 * the login password for the paired User; it is never a column here.
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'password'])]
class Guardian extends Model
{
    /** @use HasFactory<\Database\Factories\GuardianFactory> */
    use HasFactory;

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
