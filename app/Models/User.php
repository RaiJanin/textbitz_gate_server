<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * A client-app account (guardian and/or student). Admin-panel staff live in the
 * separate `x08` table (App\Models\AdminUser) — never here.
 *
 * Every User has exactly one Guardian profile, kept in sync by
 * App\Support\GuardianAccount (see App\Observers\UserObserver).
 */
#[Fillable(['name', 'email', 'phone_number', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function studentAccount(): HasOne
    {
        return $this->hasOne(StudentAccount::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * The roles this account currently holds ('guardian' and/or 'student').
     *
     * @return list<string>
     */
    public function roles(): array
    {
        $roles = [];

        if ($this->guardian()->exists()) {
            $roles[] = NotificationPreference::ROLE_GUARDIAN;
        }

        if ($this->studentAccount()->exists()) {
            $roles[] = NotificationPreference::ROLE_STUDENT;
        }

        return $roles;
    }

    /**
     * Students this account is allowed to see attendance for.
     *
     * @return Collection<int, Student>
     */
    public function visibleStudents(): Collection
    {
        $students = collect();

        $guardian = $this->guardian;
        if ($guardian) {
            $students = $students->merge($guardian->students()->with('school')->get());
        }

        $studentAccount = $this->studentAccount;
        if ($studentAccount) {
            $studentAccount->loadMissing('student.school');
            if ($studentAccount->student) {
                $students->push($studentAccount->student);
            }
        }

        return $students->unique('id')->values();
    }

    public function canViewStudent(Student $student): bool
    {
        return $this->visibleStudents()->contains('id', $student->id);
    }

    /**
     * @return Collection<int, int>
     */
    public function schoolIds(): Collection
    {
        return $this->visibleStudents()->pluck('school_id')->unique()->values();
    }

    /**
     * Fetch (creating defaults if needed) the notification preferences for a role.
     */
    public function preferencesFor(string $role): NotificationPreference
    {
        return $this->notificationPreferences()->firstOrCreate(
            ['role' => $role],
            ['arrival' => true, 'departure' => true, 'late_alert' => true, 'weekly_summary' => true],
        );
    }
}
