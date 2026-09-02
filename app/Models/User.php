<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone_number', 'password', 'school_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
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
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Only staff accounts flagged `is_admin` may open the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * A school-scoped admin only sees their own school; a super-admin
     * (is_admin, no school) sees every school.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_admin && $this->school_id === null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
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
