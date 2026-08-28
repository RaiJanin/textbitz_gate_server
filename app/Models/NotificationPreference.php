<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'role', 'arrival', 'departure', 'late_alert', 'weekly_summary'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationPreferenceFactory> */
    use HasFactory;

    const ROLE_GUARDIAN = 'guardian';
    const ROLE_STUDENT = 'student';

    protected function casts(): array
    {
        return [
            'arrival' => 'boolean',
            'departure' => 'boolean',
            'late_alert' => 'boolean',
            'weekly_summary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether a push of the given kind is permitted by this preference set.
     */
    public function allows(string $kind): bool
    {
        return (bool) ($this->getAttribute($kind) ?? false);
    }
}
