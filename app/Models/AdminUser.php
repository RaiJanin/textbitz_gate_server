<?php

namespace App\Models;

use Database\Factories\AdminUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A Filament admin-panel account, stored in the `x08` table — entirely separate
 * from `users` (client-app guardian/student accounts). Being a row here *is*
 * being an admin; `school_id` null means super-admin (sees every school).
 */
#[Fillable(['name', 'email', 'phone_number', 'password', 'school_id'])]
#[Hidden(['password', 'remember_token'])]
class AdminUser extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminUserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'x08';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /** Super-admins have no school and see every school's data. */
    public function isSuperAdmin(): bool
    {
        return $this->school_id === null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
